<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Paiement\Encaissement;
use App\Paiement\PaytechPasserelle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Notification de paiement (IPN) envoyée par PayTech au serveur de KaarPro.
 * C'est elle, et non le retour du client sur le site, qui confirme le paiement.
 */
class PaytechController extends Controller
{
    public function __invoke(Request $request, Encaissement $encaissement): Response
    {
        $donnees = $request->all();

        if (! PaytechPasserelle::notificationAuthentique($donnees)) {
            Log::warning('IPN PayTech rejetée : signature invalide.', ['ref_command' => $donnees['ref_command'] ?? null]);

            return response('IPN KO : signature invalide', 403);
        }

        $paiement = Paiement::where('reference', $donnees['ref_command'] ?? '')->first();
        if (! $paiement) {
            return response('IPN KO : commande inconnue', 404);
        }

        $evenement = $donnees['type_event'] ?? '';
        if ($evenement === 'sale_complete') {
            // En production, le montant payé doit être celui de la commande (en test, PayTech débite 100 à 150 FCFA)
            $paye = (int) ($donnees['final_item_price'] ?? $donnees['item_price'] ?? 0);
            if (($donnees['env'] ?? 'prod') === 'prod' && $paye < $paiement->montant_total) {
                Log::warning('IPN PayTech : montant inférieur à la commande.', ['reference' => $paiement->reference, 'paye' => $paye]);

                return response('IPN KO : montant incorrect', 422);
            }
            $encaissement->confirmer($paiement, true, $donnees['token'] ?? $paiement->reference_operateur);
        } elseif ($evenement === 'sale_canceled') {
            $encaissement->confirmer($paiement, false, $donnees['token'] ?? null);
        }

        // PayTech attend une réponse rapide ; une notification déjà traitée est simplement acquittée
        return response($paiement->refresh()->statut === StatutPaiement::EnAttente ? 'IPN reçue' : 'IPN OK');
    }
}
