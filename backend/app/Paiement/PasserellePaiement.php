<?php

namespace App\Paiement;

use App\Models\Paiement;

/**
 * Point d'entrée vers un opérateur de paiement mobile (Wave, Orange Money).
 * L'opérateur renvoie une page de paiement ; il confirme ensuite le résultat à KaarPro.
 */
interface PasserellePaiement
{
    /** Ouvre la transaction chez l'opérateur et renvoie l'adresse de la page où le client valide le paiement. */
    public function initier(Paiement $paiement): string;
}
