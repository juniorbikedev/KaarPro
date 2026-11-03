<?php

namespace App\Paiement;

use App\Models\Paiement;

/**
 * Passerelle de développement : la « page de l'opérateur » est une page de l'interface KaarPro
 * où le client choisit de valider ou de refuser le paiement. Aucun argent n'est débité.
 * Elle sera remplacée par les API Wave et Orange Money une fois les comptes marchands obtenus.
 */
class SimulateurPaiement implements PasserellePaiement
{
    public function initier(Paiement $paiement): string
    {
        return config('kaarpro.frontend_url')."/paiement/{$paiement->reference}";
    }
}
