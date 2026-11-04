<?php

namespace App\Models;

use App\Enums\StatutDemande;
use App\Enums\StatutPaiement;
use App\Enums\TypeDemande;
use App\Enums\TypeEtatDesLieux;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Demande extends Model
{
    /** @use HasFactory<\Database\Factories\DemandeFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id', 'vehicule_id', 'partenaire_id', 'type', 'date_debut', 'date_fin', 'nb_jours',
        'montant', 'montant_caution', 'message', 'statut', 'motif_refus', 'repondue_le',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeDemande::class,
            'statut' => StatutDemande::class,
            'date_debut' => 'date:Y-m-d',
            'date_fin' => 'date:Y-m-d',
            'nb_jours' => 'integer',
            'montant' => 'integer',
            'montant_caution' => 'integer',
            'repondue_le' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(Partenaire::class);
    }

    public function contrat(): HasOne
    {
        return $this->hasOne(Contrat::class);
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function paiementReussi(): HasOne
    {
        return $this->hasOne(Paiement::class)->where('statut', StatutPaiement::Reussi);
    }

    public function etatsDesLieux(): HasMany
    {
        return $this->hasMany(EtatDesLieux::class);
    }

    public function avis(): HasOne
    {
        return $this->hasOne(Avis::class);
    }

    /** La transaction est terminée : véhicule loué rendu, ou véhicule vendu remis à l'acheteur. */
    public function estTerminee(): bool
    {
        $dernier = $this->type === TypeDemande::Location ? TypeEtatDesLieux::Retour : TypeEtatDesLieux::Remise;

        return $this->statut === StatutDemande::Payee
            && $this->etatsDesLieux->contains(fn ($e) => $e->type === $dernier && $e->estRealise());
    }

    public function estEnAttente(): bool
    {
        return $this->statut === StatutDemande::EnAttente;
    }
}
