<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Partenaire */
class PartenaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom_commercial' => $this->nom_commercial,
            'type_activite' => $this->type_activite->value,
            'peut_vendre' => $this->type_activite->permetLaVente(),
            'peut_louer' => $this->type_activite->permetLaLocation(),
            'statut_validation' => $this->statut_validation->value,
            'motif_refus' => $this->motif_refus,
            'valide_le' => $this->valide_le?->toIso8601String(),
            'utilisateur' => new UtilisateurResource($this->whenLoaded('user')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
