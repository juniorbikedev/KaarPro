<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Enums\TypeActivite;
use App\Enums\TypeDocument;
use App\Http\Controllers\Controller;
use App\Http\Resources\UtilisateurResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InscriptionController extends Controller
{
    /** Création d'un compte client (cas « Créer un compte »). */
    public function client(Request $request): JsonResponse
    {
        $donnees = $request->validate($this->reglesCompte());

        $user = User::create([...$donnees, 'role' => Role::Client]);

        return $this->reponseAvecJeton($user, 201);
    }

    /** Création d'un compte partenaire : type d'activité et pièce d'identité inclus. */
    public function partenaire(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            ...$this->reglesCompte(),
            'nom_commercial' => ['required', 'string', 'max:150'],
            'type_activite' => ['required', Rule::enum(TypeActivite::class)],
            'piece_identite' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $user = DB::transaction(function () use ($donnees, $request) {
            $user = User::create([
                'nom' => $donnees['nom'],
                'email' => $donnees['email'],
                'telephone' => $donnees['telephone'] ?? null,
                'password' => $donnees['password'],
                'role' => Role::Vendeur,
            ]);

            $partenaire = $user->partenaire()->create([
                'nom_commercial' => $donnees['nom_commercial'],
                'type_activite' => $donnees['type_activite'],
            ]);

            $fichier = $request->file('piece_identite');
            $partenaire->documents()->create([
                'type' => TypeDocument::PieceIdentite,
                'chemin' => $fichier->store("documents/partenaires/{$partenaire->id}", 'local'),
                'nom_original' => $fichier->getClientOriginalName(),
            ]);

            return $user;
        });

        return $this->reponseAvecJeton($user->load('partenaire'), 201);
    }

    private function reglesCompte(): array
    {
        return [
            'nom' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    private function reponseAvecJeton(User $user, int $statut): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken('kaarpro')->plainTextToken,
            'utilisateur' => new UtilisateurResource($user),
        ], $statut);
    }
}
