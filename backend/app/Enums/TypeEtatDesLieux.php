<?php

namespace App\Enums;

enum TypeEtatDesLieux: string
{
    case Depart = 'depart';         // remise du véhicule loué au client
    case Retour = 'retour';         // restitution du véhicule loué
    case Remise = 'remise';         // remise du véhicule vendu à l'acheteur
    case Inspection = 'inspection'; // contrôle d'un véhicule publié par rapport à ses documents

    public function libelle(): string
    {
        return match ($this) {
            self::Depart => 'État des lieux de départ',
            self::Retour => 'État des lieux de retour',
            self::Remise => 'Contrôle avant remise',
            self::Inspection => 'Inspection du véhicule',
        };
    }
}
