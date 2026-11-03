<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\Paiement;
use App\Notifications\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cas « Suivre les paiements » et « Restituer la caution » (administrateur). */
class PaiementController extends Controller
{
    private const RELATIONS = ['demande.vehicule', 'demande.partenaire', 'client'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['statut' => ['nullable', Rule::enum(StatutPaiement::class)]]);

        $paiements = Paiement::query()
            ->when($request->query('statut'), fn ($q, $statut) => $q->where('statut', $statut))
            ->with(self::RELATIONS)
            ->latest()
            ->get();
        $reussis = Paiement::where('statut', StatutPaiement::Reussi);

        return response()->json([
            'data' => PaiementResource::collection($paiements),
            'totaux' => [
                'encaisse' => (int) (clone $reussis)->sum('montant_total'),
                'commission' => (int) (clone $reussis)->sum('commission'),
                'cautions_bloquees' => (int) (clone $reussis)->where('statut_caution', Paiement::CAUTION_BLOQUEE)->sum('montant_caution'),
            ],
        ]);
    }

    /** Restitue la caution au client, en retenant éventuellement une somme pour les dommages constatés. */
    public function restituerCaution(Request $request, Paiement $paiement): PaiementResource
    {
        abort_unless($paiement->statut_caution === Paiement::CAUTION_BLOQUEE, 422, 'Aucune caution bloquée pour ce paiement.');

        $donnees = $request->validate([
            'caution_retenue' => ['required', 'integer', 'min:0', 'max:'.$paiement->montant_caution],
            'motif_retenue' => ['required_unless:caution_retenue,0', 'nullable', 'string', 'max:255'],
        ]);

        $paiement->update([
            'statut_caution' => Paiement::CAUTION_RESTITUEE,
            'caution_retenue' => $donnees['caution_retenue'],
            'motif_retenue' => $donnees['caution_retenue'] > 0 ? $donnees['motif_retenue'] : null,
            'caution_traitee_le' => now(),
        ]);

        $rendu = number_format($paiement->montant_caution - $paiement->caution_retenue, 0, ',', ' ');
        $retenue = $paiement->motif_retenue ? " (retenue : {$paiement->motif_retenue})" : '';
        $paiement->client->notify(new Alerte('caution', 'Caution restituée',
            "{$rendu} FCFA de votre caution vous sont restitués{$retenue}.", '/demandes'));

        return new PaiementResource($paiement->load(self::RELATIONS));
    }
}
