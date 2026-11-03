<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'nom_original' => $this->nom_original,
            'est_verifie' => $this->est_verifie,
            'depose_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
