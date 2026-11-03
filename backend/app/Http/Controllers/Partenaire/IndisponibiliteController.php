<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use App\Models\Indisponibilite;
use App\Models\Vehicule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Cas « Gérer le calendrier de disponibilité ». */
class IndisponibiliteController extends Controller
{
    public function store(Request $request, Vehicule $vehicule): JsonResponse
    {
        $this->verifierProprietaire($request, $vehicule);
        abort_unless($vehicule->mode_offre->permetLaLocation(), 422, "Le calendrier ne concerne que les véhicules à louer.");

        $donnees = $request->validate([
            'date_debut' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'date_fin' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_debut'],
            'motif' => ['nullable', 'string', 'max:120'],
        ]);

        if (! $vehicule->estLibreEntre($donnees['date_debut'], $donnees['date_fin'])) {
            throw ValidationException::withMessages(['date_debut' => 'Cette période chevauche une indisponibilité déjà enregistrée.']);
        }

        $indisponibilite = $vehicule->indisponibilites()->create($donnees);

        return response()->json(['data' => [
            'id' => $indisponibilite->id,
            'date_debut' => $indisponibilite->date_debut->toDateString(),
            'date_fin' => $indisponibilite->date_fin->toDateString(),
            'motif' => $indisponibilite->motif,
        ]], 201);
    }

    public function destroy(Request $request, Vehicule $vehicule, Indisponibilite $indisponibilite): JsonResponse
    {
        $this->verifierProprietaire($request, $vehicule);
        abort_unless($indisponibilite->vehicule_id === $vehicule->id, 404);
        $indisponibilite->delete();

        return response()->json(['message' => 'Période supprimée.']);
    }

    private function verifierProprietaire(Request $request, Vehicule $vehicule): void
    {
        abort_unless($vehicule->partenaire_id === $request->user()->partenaire->id, 404);
    }
}
