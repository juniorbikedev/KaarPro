<?php

namespace App\Http\Controllers\Partenaire;

use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaiementResource;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cas « Consulter ses encaissements » : montants reçus, commission retenue, net qui revient au partenaire. */
class PaiementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $paiements = Paiement::where('statut', StatutPaiement::Reussi)
            ->whereHas('demande', fn ($q) => $q->where('partenaire_id', $request->user()->partenaire->id))
            ->with(['demande.vehicule', 'client'])
            ->latest('paye_le')
            ->get();

        return response()->json([
            'data' => PaiementResource::collection($paiements),
            'totaux' => [
                'montant' => $paiements->sum('montant'),
                'commission' => $paiements->sum('commission'),
                'montant_partenaire' => $paiements->sum('montant_partenaire'),
            ],
        ]);
    }
}
