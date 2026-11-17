<?php

namespace App\Http\Middleware;

use App\Models\Partenaire;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espace « Parc TerangaAuto » de l'administrateur : les routes de l'espace partenaire y agissent sur le
 * profil partenaire interne de TerangaAuto (créé au premier accès).
 */
class ParcTerangaAuto
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('partenaire', Partenaire::interne($request->user()));

        return $next($request);
    }
}
