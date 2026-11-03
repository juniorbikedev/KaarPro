<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Limite une route à un ou plusieurs rôles : ->middleware('role:admin') ou 'role:admin,agent'.
 */
class VerifierRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            return response()->json(['message' => "Vous n'avez pas accès à cette fonction."], 403);
        }

        return $next($request);
    }
}
