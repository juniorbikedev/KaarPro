<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Vehicule */
class VehiculeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'immatriculation' => $this->immatriculation,
            'marque' => $this->marque,
            'modele' => $this->modele,
            'annee' => $this->annee,
            'categorie' => $this->categorie->value,
            'carburant' => $this->carburant,
            'boite' => $this->boite,
            'places' => $this->places,
            'kilometrage' => $this->kilometrage,
            'ville' => $this->ville,
            'description' => $this->description,
            'mode_offre' => $this->mode_offre->value,
            'a_louer' => $this->mode_offre->permetLaLocation(),
            'a_vendre' => $this->mode_offre->permetLaVente(),
            'prix_jour' => $this->prix_jour,
            'prix_vente' => $this->prix_vente,
            'montant_caution' => $this->montant_caution,
            'etat' => $this->etat->value,
            'statut_publication' => $this->statut_publication,
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => route('photos.show', $photo),
            ])),
            'partenaire' => $this->whenLoaded('partenaire', fn () => [
                'id' => $this->partenaire->id,
                'nom_commercial' => $this->partenaire->nom_commercial,
            ]),
            'indisponibilites' => $this->whenLoaded('indisponibilites', fn () => $this->indisponibilites->map(fn ($i) => [
                'id' => $i->id,
                'date_debut' => $i->date_debut->toDateString(),
                'date_fin' => $i->date_fin->toDateString(),
                'motif' => $i->motif,
                'reservation' => $i->demande_id !== null,
            ])),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
