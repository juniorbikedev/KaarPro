<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Contrat */
class ContratResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'demande_id' => $this->demande_id,
            'statut' => $this->signe_client_le ? 'signe' : 'en_attente_signature',
            ...$this->donnees,
            'signe_partenaire_le' => $this->signe_partenaire_le?->toIso8601String(),
            'signe_client_le' => $this->signe_client_le?->toIso8601String(),
            'genere_le' => $this->created_at?->toIso8601String(),
        ];
    }
}
