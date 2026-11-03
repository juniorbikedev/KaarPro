<?php

namespace App\Enums;

enum StatutDemande: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    case Annulee = 'annulee';
    case Payee = 'payee';
}
