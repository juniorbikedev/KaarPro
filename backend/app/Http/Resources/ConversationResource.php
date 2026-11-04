<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $moi = $request->user();
        $estClient = $this->client_id === $moi->id;

        return [
            'id' => $this->id,
            'vehicule' => [
                'id' => $this->vehicule->id,
                'marque' => $this->vehicule->marque,
                'modele' => $this->vehicule->modele,
            ],
            // L'interlocuteur : le partenaire pour le client, le client pour le partenaire
            'interlocuteur' => $estClient ? $this->partenaire->nom_commercial : $this->client->nom,
            'interlocuteur_id' => $estClient ? $this->partenaire->user_id : $this->client_id,
            'dernier_message' => $this->whenLoaded('dernierMessage', fn () => $this->dernierMessage ? [
                'contenu' => $this->dernierMessage->contenu,
                'de_moi' => $this->dernierMessage->auteur_id === $moi->id,
                'cree_le' => $this->dernierMessage->created_at->toIso8601String(),
            ] : null),
            'non_lus' => $this->whenHas('non_lus', fn () => (int) $this->non_lus),
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'contenu' => $m->contenu,
                'de_moi' => $m->auteur_id === $moi->id,
                'lu' => $m->lu_le !== null,
                'cree_le' => $m->created_at->toIso8601String(),
            ])),
        ];
    }
}
