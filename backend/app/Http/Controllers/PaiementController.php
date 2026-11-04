<?php

namespace App\Http\Controllers;

use App\Enums\Operateur;
use App\Enums\StatutDemande;
use App\Http\Resources\PaiementResource;
use App\Models\Demande;
use App\Models\Paiement;
use App\Paiement\Encaissement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Cas « Payer par Wave ou Orange Money » (client). */
class PaiementController extends Controller
{
    private const RELATIONS = ['demande.vehicule', 'demande.partenaire'];

    public function store(Request $request, Demande $demande, Encaissement $encaissement): JsonResponse
    {
        abort_unless($demande->client_id === $request->user()->id, 404);
        abort_unless($demande->statut === StatutDemande::Acceptee, 422, 'Seule une demande acceptée et non payée peut être réglée.');
        abort_unless($demande->contrat?->signe_client_le, 422, 'Signez le contrat avant de payer.');

        $request->merge(['telephone' => preg_replace('/[\s.-]/', '', (string) $request->input('telephone'))]);
        $donnees = $request->validate([
            'operateur' => ['required', Rule::enum(Operateur::class)],
            // Numéro sénégalais : 70, 75, 76, 77 ou 78 suivi de 7 chiffres, avec ou sans +221
            'telephone' => ['required', 'regex:/^(\+221)?7[05678]\d{7}$/'],
        ]);

        try {
            [$paiement, $url] = $encaissement->ouvrir($demande, $donnees['operateur'], $donnees['telephone']);
        } catch (RuntimeException $erreur) {
            report($erreur);
            abort(502, 'Le service de paiement est momentanément indisponible. Réessayez dans quelques minutes.');
        }

        return response()->json([
            'data' => new PaiementResource($paiement->refresh()->load(self::RELATIONS)),
            'url_paiement' => $url,
        ], 201);
    }

    public function show(Request $request, Paiement $paiement): PaiementResource
    {
        abort_unless($paiement->client_id === $request->user()->id, 404);

        return new PaiementResource($paiement->load(self::RELATIONS));
    }

    /** Page de l'opérateur simulée : le client valide ou refuse. N'existe qu'avec la passerelle « simulateur ». */
    public function simuler(Request $request, Paiement $paiement, Encaissement $encaissement): PaiementResource
    {
        abort_unless(config('kaarpro.paiement.passerelle') === 'simulateur', 404);
        abort_unless($paiement->client_id === $request->user()->id, 404);

        $donnees = $request->validate(['resultat' => ['required', Rule::in(['reussi', 'echoue'])]]);
        $reussi = $donnees['resultat'] === 'reussi';

        $paiement = $encaissement->confirmer($paiement, $reussi, $reussi ? 'SIM-'.now()->format('YmdHis') : null);

        return new PaiementResource($paiement->load(self::RELATIONS));
    }
}
