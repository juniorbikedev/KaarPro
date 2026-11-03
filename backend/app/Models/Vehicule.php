<?php

namespace App\Models;

use App\Enums\CategorieVehicule;
use App\Enums\EtatVehicule;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vehicule extends Model
{
    /** @use HasFactory<\Database\Factories\VehiculeFactory> */
    use HasFactory;

    public const PUBLIE = 'publie';
    public const RETIRE = 'retire';

    protected $fillable = [
        'immatriculation', 'marque', 'modele', 'annee', 'categorie', 'carburant', 'boite', 'places',
        'kilometrage', 'ville', 'description', 'mode_offre', 'prix_jour', 'prix_vente', 'montant_caution',
        'etat', 'statut_publication',
    ];

    protected function casts(): array
    {
        return [
            'categorie' => CategorieVehicule::class,
            'mode_offre' => TypeActivite::class,
            'etat' => EtatVehicule::class,
            'annee' => 'integer',
            'places' => 'integer',
            'kilometrage' => 'integer',
            'prix_jour' => 'integer',
            'prix_vente' => 'integer',
            'montant_caution' => 'integer',
        ];
    }

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PhotoVehicule::class)->orderBy('ordre');
    }

    public function indisponibilites(): HasMany
    {
        return $this->hasMany(Indisponibilite::class)->orderBy('date_debut');
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(Demande::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** Véhicules visibles dans le catalogue : publiés, non vendus, d'un partenaire validé. */
    public function scopeVisibles(Builder $requete): Builder
    {
        return $requete
            ->where('statut_publication', self::PUBLIE)
            ->where('etat', '!=', EtatVehicule::Vendu)
            ->whereHas('partenaire', fn ($q) => $q->where('statut_validation', StatutValidation::Valide));
    }

    /** Véhicules sans période d'indisponibilité qui chevauche [début, fin]. */
    public function scopeLibresEntre(Builder $requete, string $debut, string $fin): Builder
    {
        return $requete->whereDoesntHave('indisponibilites', fn ($q) => $q
            ->where('date_debut', '<=', $fin)
            ->where('date_fin', '>=', $debut));
    }

    public function estLibreEntre(string $debut, string $fin, ?int $saufIndisponibilite = null): bool
    {
        return ! $this->indisponibilites()
            ->when($saufIndisponibilite, fn ($q, $id) => $q->whereKeyNot($id))
            ->where('date_debut', '<=', $fin)
            ->where('date_fin', '>=', $debut)
            ->exists();
    }
}
