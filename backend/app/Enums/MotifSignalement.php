<?php

namespace App\Enums;

enum MotifSignalement: string
{
    case AnnonceTrompeuse = 'annonce_trompeuse';
    case Fraude = 'fraude';
    case Comportement = 'comportement';
    case Autre = 'autre';
}
