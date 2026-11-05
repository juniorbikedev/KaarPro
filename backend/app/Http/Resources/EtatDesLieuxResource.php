<?php

namespace App\Http\Resources;

use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use App\Http\Controllers\Agent\MissionController;
use App\Models\EtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin \App\Models\EtatDesLieux */
class EtatDesLieuxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $demande = $this->demande;
        $vehicule = $this->vehicule;
        $retour = $this->type === TypeEtatDesLieux::Retour;
        $inspection = $this->type === TypeEtatDesLieux::Inspection;
        $depart = $retour ? $demande->etatsDesLieux->first(fn ($e) => $e->type === TypeEtatDesLieux::Depart) : null;
        $paiement = $demande?->paiementReussi;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'libelle' => $this->type->libelle(),
            'statut' => $this->estRealise() ? 'realise' : ($this->agent_id ? 'a_faire' : 'a_attribuer'),
            'agent' => $this->agent ? ['id' => $this->agent->id, 'nom' => $this->agent->nom] : null,
            'consigne' => $this->consigne,
            // Mission confiée par l'administrateur (sinon prise par l'agent lui-même)
            'confiee_par_admin' => $this->attribuee_par !== null && $this->attribuee_par !== $this->agent_id,
            'attribuee_le' => $this->attribuee_le?->toIso8601String(),
            'kilometrage' => $this->kilometrage,
            'niveau_carburant' => $this->niveau_carburant,
            'observations' => $this->observations,
            'dommages' => $this->dommages,
            'montant_dommages' => $this->montant_dommages,
            'realise_le' => $this->realise_le?->toIso8601String(),
            'photos' => $this->whenLoaded('photos', fn () => self::photos($this->resource)),
            // Le retour ne peut être fait qu'après le départ
            'depart_realise' => ! $retour || (bool) $depart?->estRealise(),
            // Au retour : le constat de départ, pour comparer
            'depart' => $depart?->estRealise() ? [
                'id' => $depart->id,
                'kilometrage' => $depart->kilometrage,
                'niveau_carburant' => $depart->niveau_carburant,
                'observations' => $depart->observations,
                'dommages' => $depart->dommages,
                'realise_le' => $depart->realise_le->toIso8601String(),
                'photos' => self::photos($depart),
            ] : null,
            // Au retour : la caution sur laquelle l'agent statue
            'caution' => $retour && $paiement ? [
                'montant' => $paiement->montant_caution,
                'statut' => $paiement->statut_caution,
                'retenue' => $paiement->caution_retenue,
                'motif' => $paiement->motif_retenue,
            ] : null,
            // Inspection : points contrôlés, résultat et documents à comparer au véhicule
            'conforme' => $this->conforme,
            'points_controle' => $inspection ? collect(MissionController::POINTS_INSPECTION)->map(fn ($libelle, $cle) => [
                'cle' => $cle,
                'libelle' => $libelle,
                'valeur' => $this->points_controle[$cle] ?? null,
            ])->values() : null,
            'documents' => $inspection && $request->user()?->aLeRole(Role::Agent, Role::Admin)
                ? $vehicule->documents->map(fn ($d) => [
                    'id' => $d->id,
                    'type' => $d->type->value,
                    'nom' => $d->nom_original,
                    'url' => URL::temporarySignedRoute('documents.signe', now()->addHour(), ['document' => $d->id]),
                ])
                : null,
            'vehicule' => [
                'id' => $vehicule->id,
                'marque' => $vehicule->marque,
                'modele' => $vehicule->modele,
                'annee' => $vehicule->annee,
                'immatriculation' => $vehicule->immatriculation,
                'ville' => $vehicule->ville,
                'kilometrage' => $vehicule->kilometrage,
            ],
            'partenaire' => [
                'nom_commercial' => $vehicule->partenaire->nom_commercial,
                'telephone' => $vehicule->partenaire->user->telephone,
            ],
            'demande' => $demande ? [
                'id' => $demande->id,
                'type' => $demande->type->value,
                'date_debut' => $demande->date_debut?->toDateString(),
                'date_fin' => $demande->date_fin?->toDateString(),
                'montant_caution' => $demande->montant_caution,
                'client' => ['nom' => $demande->client->nom, 'telephone' => $demande->client->telephone],
            ] : null,
        ];
    }

    /** Les photos sont privées : un lien signé valable une heure permet de les afficher dans une balise <img>. */
    private static function photos(EtatDesLieux $constat): array
    {
        return $constat->photos->map(fn ($photo) => [
            'id' => $photo->id,
            'url' => URL::temporarySignedRoute('etats-des-lieux.photo', now()->addHour(), ['photo' => $photo->id]),
        ])->all();
    }
}
