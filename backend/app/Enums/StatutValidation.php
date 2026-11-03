<?php

namespace App\Enums;

enum StatutValidation: string
{
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    case Refuse = 'refuse';
}
