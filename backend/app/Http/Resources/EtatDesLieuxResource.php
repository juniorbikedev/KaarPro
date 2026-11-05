<?php

namespace App\Http\Resources;

use App\Enums\TypeEtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/** @mixin \App\Models\EtatDesLieux */
class EtatDesLieuxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $demande = $this->demande;
        $vehicule = $demande->vehicule;

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
            // Les photos sont privées : un lien signé valable une heure permet de les afficher dans une balise <img>
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => URL::temporarySignedRoute('etats-des-lieux.photo', now()->addHour(), ['photo' => $photo->id]),
            ])),
            // Le retour ne peut être fait qu'après le départ
            'depart_realise' => $this->type !== TypeEtatDesLieux::Retour
                || $demande->etatsDesLieux->contains(fn ($e) => $e->type === TypeEtatDesLieux::Depart && $e->estRealise()),
            'demande' => [
                'id' => $demande->id,
                'type' => $demande->type->value,
                'date_debut' => $demande->date_debut?->toDateString(),
                'date_fin' => $demande->date_fin?->toDateString(),
                'montant_caution' => $demande->montant_caution,
                'vehicule' => [
                    'marque' => $vehicule->marque,
                    'modele' => $vehicule->modele,
                    'immatriculation' => $vehicule->immatriculation,
                    'ville' => $vehicule->ville,
                    'kilometrage' => $vehicule->kilometrage,
                ],
                'client' => ['nom' => $demande->client->nom, 'telephone' => $demande->client->telephone],
                'partenaire' => ['nom_commercial' => $demande->partenaire->nom_commercial, 'telephone' => $demande->partenaire->user->telephone],
            ],
        ];
    }
}
