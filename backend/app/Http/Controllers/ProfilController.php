<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TypeActivite;
use App\Http\Resources\UtilisateurResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Cas « Gérer son profil », commun aux quatre acteurs. */
class ProfilController extends Controller
{
    public function show(Request $request): UtilisateurResource
    {
        return new UtilisateurResource($request->user()->load('partenaire'));
    }

    public function update(Request $request): UtilisateurResource
    {
        $donnees = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:120'],
            'telephone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'password' => ['sometimes', 'required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update($donnees);

        return $this->show($request);
    }

    /** Le partenaire peut changer d'activité : vente, location ou les deux. */
    public function typeActivite(Request $request): UtilisateurResource
    {
        abort_unless($request->user()->role === Role::Vendeur, 403, 'Réservé aux vendeurs et aux loueurs.');

        $donnees = $request->validate([
            'type_activite' => ['required', Rule::enum(TypeActivite::class)],
        ]);

        $request->user()->partenaire->update($donnees);

        return $this->show($request);
    }
}
