<?php

namespace App\Models;

use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Vendeur/loueur : particulier ou agence qui propose ses véhicules sur KaarPro.
 */
class Partenaire extends Model
{
    /** @use HasFactory<\Database\Factories\PartenaireFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_commercial',
        'type_activite',
        'statut_validation',
        'motif_refus',
        'valide_le',
    ];

    protected function casts(): array
    {
        return [
            'type_activite' => TypeActivite::class,
            'statut_validation' => StatutValidation::class,
            'valide_le' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function vehicules(): HasMany
    {
        return $this->hasMany(Vehicule::class);
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function estValide(): bool
    {
        return $this->statut_validation === StatutValidation::Valide;
    }
}
