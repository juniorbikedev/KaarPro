<?php

namespace App\Http\Controllers\Agent;

use App\Enums\EtatVehicule;
use App\Enums\MotifSignalement;
use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use App\Http\Controllers\Controller;
use App\Http\Resources\EtatDesLieuxResource;
use App\Models\EtatDesLieux;
use App\Models\Paiement;
use App\Models\Signalement;
use App\Models\User;
use App\Notifications\Alerte;
use App\Paiement\Caution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Missions de l'agent : états des lieux de départ et de retour (avec décision sur la caution),
 * contrôle avant la remise d'un véhicule vendu, inspection d'un véhicule publié.
 */
class MissionController extends Controller
{
    public const RELATIONS = [
        'agent', 'photos', 'vehicule.partenaire.user', 'vehicule.documents',
        'demande.client', 'demande.partenaire.user', 'demande.paiementReussi', 'demande.etatsDesLieux.photos',
    ];

    public const CARBURANT = ['vide', '1/4', '1/2', '3/4', 'plein'];

    /** Points vérifiés lors de l'inspection d'un véhicule publié. */
    public const POINTS_INSPECTION = [
        'immatriculation' => "L'immatriculation correspond à la carte grise",
        'carte_grise' => 'La carte grise correspond au véhicule (marque, modèle, année)',
        'assurance' => "L'assurance est valide et couvre ce véhicule",
        'annonce' => "L'annonce (photos, kilométrage, état) correspond au véhicule",
    ];

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
            ->sortBy(fn (EtatDesLieux $e) => self::echeance($e))
            ->values();

        return EtatDesLieuxResource::collection($missions);
    }

    /** Les missions les plus proches dans le temps d'abord (une inspection : dès sa création). */
    public static function echeance(EtatDesLieux $mission): int
    {
        $date = match ($mission->type) {
            TypeEtatDesLieux::Retour => $mission->demande?->date_fin,
            TypeEtatDesLieux::Depart, TypeEtatDesLieux::Remise => $mission->demande?->date_debut,
            TypeEtatDesLieux::Inspection => null,
        };

        return ($date ?? $mission->created_at)->timestamp;
    }

    public function prendre(Request $request, EtatDesLieux $etatDesLieux): EtatDesLieuxResource
    {
        abort_if($etatDesLieux->agent_id !== null, 422, 'Cette mission est déjà prise par un agent.');

        $etatDesLieux->update(['agent_id' => $request->user()->id, 'attribuee_par' => $request->user()->id, 'attribuee_le' => now()]);

        return new EtatDesLieuxResource($etatDesLieux->load(self::RELATIONS));
    }

    public function realiser(Request $request, EtatDesLieux $etatDesLieux, Caution $caution): EtatDesLieuxResource
    {
        abort_unless($etatDesLieux->agent_id === $request->user()->id, 404);
        abort_if($etatDesLieux->estRealise(), 422, 'Cet état des lieux est déjà enregistré.');

        $etatDesLieux->load(self::RELATIONS);

        if ($etatDesLieux->type === TypeEtatDesLieux::Inspection) {
            $this->inspecter($request, $etatDesLieux);
        } else {
            $this->constater($request, $etatDesLieux, $caution);
        }

        return new EtatDesLieuxResource($etatDesLieux->refresh()->load(self::RELATIONS));
    }

    /** État des lieux de départ, de retour, ou contrôle avant remise. */
    private function constater(Request $request, EtatDesLieux $etatDesLieux, Caution $caution): void
    {
        $demande = $etatDesLieux->demande;
        $vehicule = $etatDesLieux->vehicule;
        $retour = $etatDesLieux->type === TypeEtatDesLieux::Retour;

        $depart = $demande->etatsDesLieux->first(fn ($e) => $e->type === TypeEtatDesLieux::Depart);
        if ($retour && ! $depart?->estRealise()) {
            throw ValidationException::withMessages(['type' => "L'état des lieux de départ doit être fait avant celui du retour."]);
        }

        // Au retour, l'agent statue sur la caution encore bloquée
        $paiement = $demande->paiementReussi;
        $statuer = $retour && $paiement?->statut_caution === Paiement::CAUTION_BLOQUEE;

        $donnees = $request->validate([
            'kilometrage' => ['required', 'integer', 'min:'.($retour ? $depart->kilometrage : $vehicule->kilometrage), 'max:2000000'],
            'niveau_carburant' => ['required', Rule::in(self::CARBURANT)],
            'observations' => ['nullable', 'string', 'max:2000'],
            'dommages' => ['nullable', 'string', 'max:2000', 'required_if:decision_caution,retenir'],
            'decision_caution' => $statuer ? ['required', Rule::in(['restituer', 'retenir'])] : ['prohibited'],
            'montant_dommages' => $statuer
                ? ['nullable', 'integer', 'required_if:decision_caution,retenir', 'min:1', 'max:'.$paiement->montant_caution]
                : ['prohibited'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ], [
            'kilometrage.min' => $retour
                ? 'Le kilométrage ne peut pas être inférieur à celui du départ (:min km).'
                : 'Le kilométrage ne peut pas être inférieur au dernier relevé (:min km).',
        ]);
        $retenue = $statuer && $donnees['decision_caution'] === 'retenir' ? (int) $donnees['montant_dommages'] : 0;

        DB::transaction(function () use ($etatDesLieux, $donnees, $request, $vehicule, $retenue, $statuer, $paiement, $caution) {
            $this->enregistrerPhotos($request, $etatDesLieux);

            $etatDesLieux->update([
                'kilometrage' => $donnees['kilometrage'],
                'niveau_carburant' => $donnees['niveau_carburant'],
                'observations' => $donnees['observations'] ?? null,
                'dommages' => $donnees['dommages'] ?? null,
                'montant_dommages' => $statuer ? $retenue : null,
                'realise_le' => now(),
            ]);

            // Le véhicule suit le constat : sorti en location au départ, de nouveau disponible au retour
            $vehicule->update(array_filter([
                'kilometrage' => $donnees['kilometrage'],
                'etat' => match ($etatDesLieux->type) {
                    TypeEtatDesLieux::Depart => EtatVehicule::EnLocation,
                    TypeEtatDesLieux::Retour => EtatVehicule::Disponible,
                    default => null,
                },
            ]));

            if ($statuer) {
                $caution->statuer($paiement, $retenue, $donnees['dommages'] ?? null);
            }
        });

        $lien = "/etats-des-lieux/{$etatDesLieux->id}";
        $texte = "{$etatDesLieux->type->libelle()} du {$vehicule->marque} {$vehicule->modele} enregistré par {$request->user()->nom}.";
        $demande->client->notify(new Alerte('etat_des_lieux', $etatDesLieux->type->libelle(), $texte, $lien));
        $demande->partenaire->user->notify(new Alerte('etat_des_lieux', $etatDesLieux->type->libelle(), $texte, $lien));
    }

    /**
     * Inspection d'un véhicule publié : l'agent compare le véhicule à sa carte grise, son assurance et son annonce.
     * Une non-conformité crée un signalement que l'administrateur traite (retrait de l'annonce, par exemple).
     */
    private function inspecter(Request $request, EtatDesLieux $inspection): void
    {
        $vehicule = $inspection->vehicule;

        $regles = [
            'kilometrage' => ['required', 'integer', 'min:0', 'max:2000000'],
            'points_controle' => ['required', 'array'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'dommages' => ['nullable', 'string', 'max:2000'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ];
        foreach (array_keys(self::POINTS_INSPECTION) as $point) {
            $regles["points_controle.{$point}"] = ['required', 'boolean'];
        }
        $donnees = $request->validate($regles);

        $points = collect(self::POINTS_INSPECTION)->keys()->mapWithKeys(fn ($p) => [$p => (bool) $donnees['points_controle'][$p]])->all();
        $conforme = ! in_array(false, $points, true);
        if (! $conforme && blank($donnees['dommages'] ?? null)) {
            throw ValidationException::withMessages(['dommages' => 'Décrivez la ou les non-conformités constatées.']);
        }

        DB::transaction(function () use ($request, $inspection, $donnees, $points, $conforme, $vehicule) {
            $this->enregistrerPhotos($request, $inspection);

            $inspection->update([
                'kilometrage' => $donnees['kilometrage'],
                'observations' => $donnees['observations'] ?? null,
                'dommages' => $conforme ? null : $donnees['dommages'],
                'points_controle' => $points,
                'conforme' => $conforme,
                'realise_le' => now(),
            ]);

            if ($conforme) {
                $vehicule->update(['verifie_le' => now()]);
            } else {
                $vehicule->update(['verifie_le' => null]);
                Signalement::create([
                    'auteur_id' => $request->user()->id,
                    'vehicule_id' => $vehicule->id,
                    'motif' => MotifSignalement::AnnonceTrompeuse,
                    'description' => "Inspection n°{$inspection->id} non conforme : {$donnees['dommages']}",
                ]);
            }
        });

        $nom = "{$vehicule->marque} {$vehicule->modele} ({$vehicule->immatriculation})";
        $lien = "/etats-des-lieux/{$inspection->id}";
        if ($conforme) {
            $vehicule->partenaire->user->notify(new Alerte('inspection', 'Véhicule vérifié',
                "Votre {$nom} a été inspecté et jugé conforme : l'annonce porte le badge « Vérifié par un agent ».", $lien));

            return;
        }

        $vehicule->partenaire->user->notify(new Alerte('inspection', 'Non-conformité relevée',
            "L'inspection de votre {$nom} a relevé une non-conformité : {$donnees['dommages']}", $lien));
        $admins = User::where('role', Role::Admin)->where('est_actif', true)->get();
        Notification::send($admins, new Alerte('signalement', 'Non-conformité relevée',
            "L'agent {$request->user()->nom} signale une non-conformité sur le {$nom}.", '/admin/signalements'));
    }

    private function enregistrerPhotos(Request $request, EtatDesLieux $mission): void
    {
        foreach ($request->file('photos') as $photo) {
            $mission->photos()->create(['chemin' => $photo->store("etats-des-lieux/{$mission->id}", 'local')]);
        }
    }
}
