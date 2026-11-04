<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Demande;
use App\Models\Vehicule;
use App\Notifications\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cas « Donner un avis » (client) et affichage des avis sur la fiche véhicule. */
class AvisController extends Controller
{
    /** Avis visibles sur un véhicule, et note moyenne de son partenaire. */
    public function index(Vehicule $vehicule): JsonResponse
    {
        $avis = $vehicule->avis()->where('est_masque', false)->with('client')->latest()->get();
        $partenaire = Avis::where('partenaire_id', $vehicule->partenaire_id)->where('est_masque', false);

        return response()->json([
            'data' => $avis->map(fn (Avis $a) => $this->presenter($a)),
            'partenaire' => [
                'moyenne' => round((float) (clone $partenaire)->avg('note'), 1),
                'nombre' => (clone $partenaire)->count(),
            ],
        ]);
    }

    public function store(Request $request, Demande $demande): JsonResponse
    {
        abort_unless($demande->client_id === $request->user()->id, 404);
        $demande->load(['etatsDesLieux', 'avis', 'vehicule', 'partenaire.user']);
        abort_unless($demande->estTerminee(), 422, "Vous pourrez donner votre avis une fois la transaction terminée.");
        abort_if($demande->avis !== null, 422, 'Vous avez déjà donné votre avis.');

        $donnees = $request->validate([
            'note' => ['required', 'integer', 'between:1,5'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        $avis = $demande->avis()->create([
            ...$donnees,
            'client_id' => $demande->client_id,
            'partenaire_id' => $demande->partenaire_id,
            'vehicule_id' => $demande->vehicule_id,
        ]);

        $demande->partenaire->user->notify(new Alerte('avis', 'Nouvel avis',
            "{$request->user()->nom} a noté le {$demande->vehicule->marque} {$demande->vehicule->modele} : {$avis->note}/5.",
            "/vehicules/{$demande->vehicule_id}"));

        return response()->json(['data' => $this->presenter($avis->load('client'))], 201);
    }

    /** Modération : l'administrateur masque un avis abusif (ou le réaffiche). */
    public function masquer(Request $request, Avis $avis): JsonResponse
    {
        $donnees = $request->validate(['est_masque' => ['required', 'boolean']]);
        $avis->update($donnees);

        return response()->json(['data' => $this->presenter($avis->load('client'))]);
    }

    private function presenter(Avis $avis): array
    {
        // Prénom et initiale du nom seulement : l'avis est public
        $morceaux = preg_split('/\s+/', trim($avis->client->nom));
        $auteur = $morceaux[0].(isset($morceaux[1]) ? ' '.mb_strtoupper(mb_substr($morceaux[1], 0, 1)).'.' : '');

        return [
            'id' => $avis->id,
            'note' => $avis->note,
            'commentaire' => $avis->commentaire,
            'auteur' => $auteur,
            'est_masque' => $avis->est_masque,
            'cree_le' => $avis->created_at?->toIso8601String(),
        ];
    }
}
