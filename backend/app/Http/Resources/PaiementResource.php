<?php

namespace App\Http\Resources;

use App\Enums\TypeEtatDesLieux;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Paiement */
class PaiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->reference,
            'operateur' => $this->operateur->value,
            'operateur_libelle' => $this->operateur->libelle(),
            'telephone' => $this->telephone,
            'statut' => $this->statut->value,
            'montant' => $this->montant,
            'montant_caution' => $this->montant_caution,
            'montant_total' => $this->montant_total,
            'taux_commission' => $this->taux_commission,
            'commission' => $this->commission,
            'montant_partenaire' => $this->montant_partenaire,
            'paye_le' => $this->paye_le?->toIso8601String(),
            'statut_caution' => $this->statut_caution,
            'caution_retenue' => $this->caution_retenue,
            'motif_retenue' => $this->motif_retenue,
            'caution_traitee_le' => $this->caution_traitee_le?->toIso8601String(),
            'demande' => $this->whenLoaded('demande', fn () => [
                'id' => $this->demande->id,
                'type' => $this->demande->type->value,
                'date_debut' => $this->demande->date_debut?->toDateString(),
                'date_fin' => $this->demande->date_fin?->toDateString(),
                'vehicule' => $this->demande->relationLoaded('vehicule')
                    ? "{$this->demande->vehicule->marque} {$this->demande->vehicule->modele}"
                    : null,
                'partenaire' => $this->demande->relationLoaded('partenaire') ? $this->demande->partenaire->nom_commercial : null,
                // Constat de retour : il conditionne la restitution de la caution et propose la retenue
                'etat_retour' => $this->demande->relationLoaded('etatsDesLieux')
                    ? $this->demande->etatsDesLieux->where('type', TypeEtatDesLieux::Retour)->map(fn ($e) => [
                        'id' => $e->id,
                        'realise' => $e->estRealise(),
                        'dommages' => $e->dommages,
                        'montant_dommages' => $e->montant_dommages,
                    ])->first()
                    : null,
            ]),
            'client' => $this->whenLoaded('client', fn () => $this->client->nom),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
