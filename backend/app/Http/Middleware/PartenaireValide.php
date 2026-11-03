<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'espace « vendeur/loueur » aux partenaires validés par l'administrateur.
 */
class PartenaireValide
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== Role::Vendeur || ! $user->partenaire) {
            return response()->json(['message' => 'Réservé aux vendeurs et aux loueurs.'], 403);
        }

        if (! $user->partenaire->estValide()) {
            return response()->json(['message' => "Votre compte partenaire n'est pas encore validé par un administrateur."], 403);
        }

        return $next($request);
    }
}
