<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Signalement;
use App\Models\Vehicule;
use App\Notifications\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cas « Traiter les signalements » (administrateur). */
class SignalementController extends Controller
{
    private const RELATIONS = ['auteur', 'vehicule.partenaire', 'utilisateur'];

    public function index(Request $request): JsonResponse
    {
        $request->validate(['statut' => ['nullable', Rule::in([Signalement::OUVERT, Signalement::TRAITE, Signalement::REJETE])]]);

        $signalements = Signalement::query()
            ->when($request->query('statut'), fn ($q, $statut) => $q->where('statut', $statut))
            ->with(self::RELATIONS)
            ->latest()
            ->get();

        return response()->json(['data' => $signalements->map(fn ($s) => $this->presenter($s))]);
    }

    /**
     * Décision de l'administrateur, avec une mesure éventuelle :
     * retirer l'annonce signalée ou désactiver le compte signalé.
     */
    public function traiter(Request $request, Signalement $signalement): JsonResponse
    {
        abort_unless($signalement->statut === Signalement::OUVERT, 422, 'Ce signalement a déjà été traité.');

        $donnees = $request->validate([
            'statut' => ['required', Rule::in([Signalement::TRAITE, Signalement::REJETE])],
            'decision' => ['required', 'string', 'max:1000'],
            'mesure' => ['nullable', Rule::in(['retirer_annonce', 'desactiver_compte'])],
        ]);
        $mesure = $donnees['statut'] === Signalement::TRAITE ? ($donnees['mesure'] ?? null) : null;

        if ($mesure === 'retirer_annonce') {
            abort_unless($signalement->vehicule, 422, 'Ce signalement ne concerne pas une annonce.');
            $signalement->vehicule->update(['statut_publication' => Vehicule::RETIRE]);
            $signalement->vehicule->partenaire->user->notify(new Alerte('signalement', 'Annonce retirée',
                "Votre annonce {$signalement->vehicule->marque} {$signalement->vehicule->modele} a été retirée à la suite d'un signalement : {$donnees['decision']}",
                '/partenaire/vehicules'));
        }
        if ($mesure === 'desactiver_compte') {
            $cible = $signalement->utilisateur;
            abort_unless($cible, 422, 'Ce signalement ne concerne pas un utilisateur.');
            abort_if($cible->is($request->user()), 422, 'Vous ne pouvez pas désactiver votre propre compte.');
            $cible->update(['est_actif' => false]);
            $cible->tokens()->delete();
        }

        $signalement->update([
            'statut' => $donnees['statut'],
            'decision' => $donnees['decision'],
            'traite_par' => $request->user()->id,
            'traite_le' => now(),
        ]);

        $signalement->auteur->notify(new Alerte('signalement', 'Signalement traité',
            "Votre signalement a été examiné : {$donnees['decision']}", null));

        return response()->json(['data' => $this->presenter($signalement->load(self::RELATIONS))]);
    }

    private function presenter(Signalement $s): array
    {
        return [
            'id' => $s->id,
            'motif' => $s->motif->value,
            'description' => $s->description,
            'statut' => $s->statut,
            'decision' => $s->decision,
            'auteur' => $s->auteur->nom,
            'cible' => $s->vehicule
                ? ['type' => 'vehicule', 'id' => $s->vehicule->id, 'libelle' => "{$s->vehicule->marque} {$s->vehicule->modele} ({$s->vehicule->partenaire->nom_commercial})", 'retiree' => $s->vehicule->statut_publication === Vehicule::RETIRE]
                : ['type' => 'utilisateur', 'id' => $s->utilisateur->id, 'libelle' => "{$s->utilisateur->nom} ({$s->utilisateur->email})", 'actif' => $s->utilisateur->est_actif],
            'cree_le' => $s->created_at?->toIso8601String(),
            'traite_le' => $s->traite_le?->toIso8601String(),
        ];
    }
}
