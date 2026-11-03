<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Http\Resources\ContratResource;
use App\Models\Contrat;
use Illuminate\Http\Request;

/** Cas « Consulter et signer le contrat ». */
class ContratController extends Controller
{
    public function show(Request $request, Contrat $contrat): ContratResource
    {
        $demande = $contrat->demande()->with('partenaire')->first();
        $user = $request->user();

        $autorise = $demande->client_id === $user->id
            || $demande->partenaire->user_id === $user->id
            || $user->aLeRole(Role::Admin);
        abort_unless($autorise, 404);

        return new ContratResource($contrat);
    }

    /** Le client signe électroniquement ; le partenaire a signé en acceptant la demande. */
    public function signer(Request $request, Contrat $contrat): ContratResource
    {
        abort_unless($contrat->demande->client_id === $request->user()->id, 404);
        abort_if($contrat->signe_client_le !== null, 422, 'Ce contrat est déjà signé.');

        $request->validate(['accepte' => ['accepted']]);
        $contrat->update(['signe_client_le' => now()]);

        return new ContratResource($contrat);
    }
}
