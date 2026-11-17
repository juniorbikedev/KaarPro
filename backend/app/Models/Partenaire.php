<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Notifications\Alerte;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

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
        'est_interne',
        'statut_validation',
        'motif_refus',
        'valide_le',
    ];

    protected function casts(): array
    {
        return [
            'type_activite' => TypeActivite::class,
            'est_interne' => 'boolean',
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

    /**
     * Profil partenaire interne de TerangaAuto, sous lequel l'entreprise publie ses propres véhicules.
     * Il est créé au premier accès d'un administrateur, validé d'office, et rattaché à son compte.
     */
    /** Personnes qui gèrent ce partenaire : son compte, ou tous les administrateurs pour le parc de TerangaAuto. */
    public function gestionnaires(): Collection
    {
        return $this->est_interne ? User::where('role', Role::Admin)->get() : collect([$this->user]);
    }

    /** Notifie le partenaire ; pour le parc, les liens de l'espace partenaire mènent à l'espace « Parc TerangaAuto ». */
    public function notifier(Alerte $alerte): void
    {
        if ($this->est_interne && $alerte->lien !== null) {
            $alerte->lien = preg_replace('#^/partenaire/#', '/admin/parc/', $alerte->lien);
        }

        Notification::send($this->gestionnaires(), $alerte);
    }

    public static function interne(User $administrateur): self
    {
        return self::firstOrCreate(['est_interne' => true], [
            'user_id' => $administrateur->id,
            'nom_commercial' => 'TerangaAuto',
            'type_activite' => TypeActivite::VenteEtLocation,
            'statut_validation' => StatutValidation::Valide,
            'valide_le' => now(),
        ]);
    }

    public function estValide(): bool
    {
        return $this->statut_validation === StatutValidation::Valide;
    }
}
