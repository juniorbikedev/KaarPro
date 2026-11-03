<?php

namespace App\Enums;

enum Role: string
{
    case Client = 'client';
    case Vendeur = 'vendeur';
    case Agent = 'agent';
    case Admin = 'admin';
}
