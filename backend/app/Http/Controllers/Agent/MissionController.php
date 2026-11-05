<?php

namespace App\Http\Controllers\Agent;

use App\Enums\EtatVehicule;
use App\Enums\TypeEtatDesLieux;
use App\Http\Controllers\Controller;
use App\Http\Resources\EtatDesLieuxResource;
use App\Models\EtatDesLieux;
use App\Notifications\Alerte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Cas « Réaliser un état des lieux » (agent) : prendre une mission, constater, photographier. */
class MissionController extends Controller
{
    public const RELATIONS = ['agent', 'photos', 'demande.vehicule', 'demande.client', 'demande.partenaire.user', 'demande.etatsDesLieux'];

    public const CARBURANT = ['vide', '1/4', '1/2', '3/4', 'plein'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['filtre' => ['nullable', Rule::in(['disponibles', 'miennes', 'realisees'])]]);
        $agent = $request->user();

        $missions = EtatDesLieux::query()
            ->with(self::RELATIONS)
            ->when($request->query('filtre', 'disponibles'), fn ($q, $filtre) => match ($filtre) {
                'disponibles' => $q->whereNull('agent_id')->whereNull('realise_le'),
                'miennes' => $q->where('agent_id', $agent->id)->whereNull('realise_le'),
                'realisees' => $q->where('agent_id', $agent->id)->whereNotNull('realise_le'),
            })
            ->get()
            // Les missions les plus proches dans le temps d'abord
            ->sortBy(fn ($e) => ($e->type === TypeEtatDesLieux::Retour ? $e->demande->date_fin : $e->demande->date_debut)?->timestamp ?? $e->created_at->timestamp)
            ->values();

        return EtatDesLieuxResource::collection($missions);
    }

    public function prendre(Request $request, EtatDesLieux $etatDesLieux): EtatDesLieuxResource
    {
        abort_if($etatDesLieux->agent_id !== null, 422, 'Cette mission est déjà prise par un agent.');

        $etatDesLieux->update(['agent_id' => $request->user()->id, 'attribuee_par' => $request->user()->id, 'attribuee_le' => now()]);

        return new EtatDesLieuxResource($etatDesLieux->load(self::RELATIONS));
    }

    public function realiser(Request $request, EtatDesLieux $etatDesLieux): EtatDesLieuxResource
    {
        abort_unless($etatDesLieux->agent_id === $request->user()->id, 404);
        abort_if($etatDesLieux->estRealise(), 422, 'Cet état des lieux est déjà enregistré.');

        $etatDesLieux->load(self::RELATIONS);
        $demande = $etatDesLieux->demande;
        $vehicule = $demande->vehicule;
        $retour = $etatDesLieux->type === TypeEtatDesLieux::Retour;

        $departFait = $demande->etatsDesLieux->contains(fn ($e) => $e->type === TypeEtatDesLieux::Depart && $e->estRealise());
        if ($retour && ! $departFait) {
            throw ValidationException::withMessages(['type' => "L'état des lieux de départ doit être fait avant celui du retour."]);
        }

        $donnees = $request->validate([
            'kilometrage' => ['required', 'integer', 'min:'.$vehicule->kilometrage, 'max:2000000'],
            'niveau_carburant' => ['required', Rule::in(self::CARBURANT)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'dommages' => ['nullable', 'string', 'max:2000'],
            // Seul le retour propose une retenue sur la caution
            'montant_dommages' => $retour
                ? ['nullable', 'integer', 'min:0', 'max:'.(int) $demande->montant_caution, 'required_with:dommages']
                : ['prohibited'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        DB::transaction(function () use ($etatDesLieux, $donnees, $request, $vehicule) {
            foreach ($request->file('photos') as $photo) {
                $etatDesLieux->photos()->create(['chemin' => $photo->store("etats-des-lieux/{$etatDesLieux->id}", 'local')]);
            }

            $etatDesLieux->update([
                'kilometrage' => $donnees['kilometrage'],
                'niveau_carburant' => $donnees['niveau_carburant'],
                'observations' => $donnees['observations'] ?? null,
                'dommages' => $donnees['dommages'] ?? null,
                'montant_dommages' => $donnees['montant_dommages'] ?? null,
                'realise_le' => now(),
            ]);

            // Le véhicule suit le constat : sorti en location au départ, de nouveau disponible au retour
            $vehicule->update(array_filter([
                'kilometrage' => $donnees['kilometrage'],
                'etat' => match ($etatDesLieux->type) {
                    TypeEtatDesLieux::Depart => EtatVehicule::EnLocation,
                    TypeEtatDesLieux::Retour => EtatVehicule::Disponible,
                    TypeEtatDesLieux::Remise => null,
                },
            ]));
        });

        $lien = "/etats-des-lieux/{$etatDesLieux->id}";
        $texte = "{$etatDesLieux->type->libelle()} du {$vehicule->marque} {$vehicule->modele} enregistré par {$request->user()->nom}.";
        $demande->client->notify(new Alerte('etat_des_lieux', $etatDesLieux->type->libelle(), $texte, $lien));
        $demande->partenaire->user->notify(new Alerte('etat_des_lieux', $etatDesLieux->type->libelle(), $texte, $lien));

        return new EtatDesLieuxResource($etatDesLieux->refresh()->load(self::RELATIONS));
    }
}
