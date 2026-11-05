<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use App\Http\Controllers\Agent\MissionController as MissionAgentController;
use App\Http\Controllers\Controller;
use App\Http\Resources\EtatDesLieuxResource;
use App\Models\EtatDesLieux;
use App\Models\User;
use App\Notifications\Alerte;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cas « Attribuer une mission à un agent » (administrateur). */
class MissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['statut' => ['nullable', Rule::in(['a_attribuer', 'a_faire', 'realise'])]]);

        $missions = EtatDesLieux::query()
            ->with(MissionAgentController::RELATIONS)
            ->when($request->query('statut'), fn ($q, $statut) => match ($statut) {
                'a_attribuer' => $q->whereNull('agent_id')->whereNull('realise_le'),
                'a_faire' => $q->whereNotNull('agent_id')->whereNull('realise_le'),
                'realise' => $q->whereNotNull('realise_le'),
            })
            ->get()
            // Les missions les plus proches dans le temps d'abord
            ->sortBy(fn ($e) => ($e->type === TypeEtatDesLieux::Retour ? $e->demande->date_fin : $e->demande->date_debut)?->timestamp ?? $e->created_at->timestamp)
            ->values();

        // Agents disponibles, avec leur zone et leur charge actuelle pour choisir à qui confier la mission
        $agents = User::where('role', Role::Agent)->where('est_actif', true)
            ->withCount(['missions as missions_en_cours' => fn ($q) => $q->whereNull('realise_le')])
            ->orderBy('nom')
            ->get()
            ->map(fn (User $a) => ['id' => $a->id, 'nom' => $a->nom, 'zone' => $a->zone, 'missions_en_cours' => $a->missions_en_cours]);

        return response()->json([
            'data' => EtatDesLieuxResource::collection($missions),
            'agents' => $agents,
        ]);
    }

    /** Confie (ou réattribue) une mission non réalisée à un agent actif, avec une consigne éventuelle. */
    public function attribuer(Request $request, EtatDesLieux $etatDesLieux): EtatDesLieuxResource
    {
        abort_if($etatDesLieux->estRealise(), 422, 'Cet état des lieux est déjà réalisé.');

        $donnees = $request->validate([
            'agent_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', Role::Agent->value)->where('est_actif', true)],
            'consigne' => ['nullable', 'string', 'max:1000'],
        ]);

        $precedent = $etatDesLieux->agent;
        $etatDesLieux->update([
            'agent_id' => $donnees['agent_id'],
            'consigne' => $donnees['consigne'] ?? null,
            'attribuee_par' => $request->user()->id,
            'attribuee_le' => now(),
        ]);
        $etatDesLieux->load(MissionAgentController::RELATIONS);

        $vehicule = $etatDesLieux->demande->vehicule;
        $objet = "{$etatDesLieux->type->libelle()} du {$vehicule->marque} {$vehicule->modele} ({$vehicule->ville})";
        $etatDesLieux->agent->notify(new Alerte('mission', 'Mission confiée',
            "L'administrateur vous confie : {$objet}.".($etatDesLieux->consigne ? " Consigne : {$etatDesLieux->consigne}" : ''),
            '/agent/missions'));

        if ($precedent && $precedent->id !== $etatDesLieux->agent_id) {
            $precedent->notify(new Alerte('mission', 'Mission réattribuée',
                "{$objet} a été confiée à un autre agent.", '/agent/missions'));
        }

        return new EtatDesLieuxResource($etatDesLieux);
    }
}
