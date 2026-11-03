<?php

namespace App\Http\Resources;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UtilisateurResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'email' => $this->email,
            'telephone' => $this->telephone,
            'role' => $this->role->value,
            'est_actif' => $this->est_actif,
            'zone' => $this->when($this->role === Role::Agent, $this->zone),
            'partenaire' => new PartenaireResource($this->whenLoaded('partenaire')),
            'cree_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
