<?php

namespace App\Enums;

enum TypeActivite: string
{
    case Vente = 'vente';
    case Location = 'location';
    case VenteEtLocation = 'vente_et_location';

    public function permetLaVente(): bool
    {
        return $this !== self::Location;
    }

    public function permetLaLocation(): bool
    {
        return $this !== self::Vente;
    }
}
