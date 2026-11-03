<?php

namespace App\Enums;

/** Opérateurs de paiement mobile acceptés. */
enum Operateur: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';

    public function libelle(): string
    {
        return match ($this) {
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
        };
    }
}
