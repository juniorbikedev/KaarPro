<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Notification affichée dans l'interface (cloche de l'en-tête).
 * Catégories : demande, contrat, paiement, caution, message.
 */
class Alerte extends Notification
{
    public function __construct(
        public string $categorie,
        public string $titre,
        public string $texte,
        public ?string $lien = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return [
            'categorie' => $this->categorie,
            'titre' => $this->titre,
            'texte' => $this->texte,
            'lien' => $this->lien,
        ];
    }
}
