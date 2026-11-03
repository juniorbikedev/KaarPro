<?php

namespace App\Enums;

enum TypeDocument: string
{
    case PieceIdentite = 'piece_identite';
    case CarteGrise = 'carte_grise';
    case Assurance = 'assurance';
}
