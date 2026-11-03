<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Demande */
class DemandeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'statut' => $this->statut->value,
            'date_debut' => $this->date_debut?->toDateString(),
            'date_fin' => $this->date_fin?->toDateString(),
            'nb_jours' => $this->nb_jours,
            'montant' => $this->montant,
            'montant_caution' => $this->montant_caution,
            'message' => $this->message,
            'motif_refus' => $this->motif_refus,
            'vehicule' => $this->whenLoaded('vehicule', fn () => [
                'id' => $this->vehicule->id,
                'marque' => $this->vehicule->marque,
                'modele' => $this->vehicule->modele,
                'annee' => $this->vehicule->annee,
                'immatriculation' => $this->vehicule->immatriculation,
                'ville' => $this->vehicule->ville,
                'photo' => $this->vehicule->relationLoaded('photos') && $this->vehicule->photos->isNotEmpty()
                    ? route('photos.show', $this->vehicule->photos->first())
                    : null,
            ]),
            // Les coordonnées du client ne sont montrées qu'au partenaire concerné
            'client' => $this->whenLoaded('client', fn () => [
                'nom' => $this->client->nom,
                'email' => $this->client->email,
                'telephone' => $this->client->telephone,
            ]),
            'partenaire' => $this->whenLoaded('partenaire', fn () => [
                'id' => $this->partenaire->id,
                'nom_commercial' => $this->partenaire->nom_commercial,
            ]),
            'contrat' => $this->whenLoaded('contrat', fn () => $this->contrat ? [
                'id' => $this->contrat->id,
                'numero' => $this->contrat->numero,
                'signe_client_le' => $this->contrat->signe_client_le?->toIso8601String(),
            ] : null),
            'cree_le' => $this->created_at?->toIso8601String(),
            'repondue_le' => $this->repondue_le?->toIso8601String(),
        ];
    }
}
