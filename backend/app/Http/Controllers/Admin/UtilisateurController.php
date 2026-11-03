<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Cas « Gérer les utilisateurs » de l'administrateur. */
class UtilisateurController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['role' => ['nullable', Rule::enum(Role::class)]]);

        $utilisateurs = User::with('partenaire')
            ->when($request->role, fn ($q, $role) => $q->where('role', $role))
            ->latest()
            ->paginate(20);

        return UtilisateurResource::collection($utilisateurs);
    }

    /** « Créer un compte agent » : seuls les administrateurs créent les agents. */
    public function storeAgent(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'zone' => ['required', 'string', 'max:120'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $agent = User::create([...$donnees, 'role' => Role::Agent]);

        return (new UtilisateurResource($agent))->response()->setStatusCode(201);
    }

    /** « Désactiver un compte » (ou le réactiver). Un compte désactivé perd ses sessions. */
    public function activation(Request $request, User $utilisateur): UtilisateurResource
    {
        $donnees = $request->validate(['est_actif' => ['required', 'boolean']]);

        abort_if($utilisateur->is($request->user()), 422, 'Vous ne pouvez pas désactiver votre propre compte.');

        $utilisateur->update($donnees);
        if (! $utilisateur->est_actif) {
            $utilisateur->tokens()->delete();
        }

        return new UtilisateurResource($utilisateur->load('partenaire'));
    }
}
