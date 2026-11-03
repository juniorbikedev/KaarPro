<?php

namespace App\Enums;

enum EtatVehicule: string
{
    case Disponible = 'disponible';
    case EnLocation = 'en_location';
    case EnMaintenance = 'en_maintenance';
    case Vendu = 'vendu';
}
