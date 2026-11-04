<?php

namespace App\Models;

use App\Enums\MotifSignalement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signalement extends Model
{
    public const OUVERT = 'ouvert';
    public const TRAITE = 'traite';
    public const REJETE = 'rejete';

    protected $fillable = ['auteur_id', 'vehicule_id', 'utilisateur_id', 'motif', 'description', 'statut', 'decision', 'traite_par', 'traite_le'];

    protected function casts(): array
    {
        return ['motif' => MotifSignalement::class, 'traite_le' => 'datetime'];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }
}
