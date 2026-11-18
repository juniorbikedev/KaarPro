<?php

namespace App\Http\Controllers;

use App\Models\Partenaire;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Partenaire au nom duquel agit la requête : le vendeur/loueur connecté, ou le parc de TerangaAuto
     * pour un administrateur (voir les middlewares PartenaireValide et ParcTerangaAuto).
     */
    protected function partenaireCourant(Request $request): Partenaire
    {
        return $request->attributes->get('partenaire') ?? $request->user()->partenaire;
    }
}
