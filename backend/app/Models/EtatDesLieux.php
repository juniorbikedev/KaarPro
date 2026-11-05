<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\TypeEtatDesLieux;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EtatDesLieux extends Model
{
    protected $table = 'etats_des_lieux';

    protected $fillable = [
        'type', 'vehicule_id', 'agent_id', 'consigne', 'attribuee_par', 'attribuee_le', 'kilometrage', 'niveau_carburant', 'observations', 'dommages', 'montant_dommages', 'conforme', 'points_controle', 'realise_le',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeEtatDesLieux::class,
            'kilometrage' => 'integer',
            'montant_dommages' => 'integer',
            'realise_le' => 'datetime',
            'attribuee_le' => 'datetime',
            'conforme' => 'boolean',
            'points_controle' => 'array',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    /** Véhicule concerné : celui de la demande, ou le véhicule inspecté. */
    public function vehicule(): BelongsTo
    {
        return $this->belongsTo(Vehicule::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function attribuePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attribuee_par');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PhotoEtatDesLieux::class);
    }

    public function estRealise(): bool
    {
        return $this->realise_le !== null;
    }

    /** Les parties (client, partenaire), un agent ou l'administrateur peuvent consulter le constat. */
    public function estVisiblePar(User $user): bool
    {
        if ($user->aLeRole(Role::Agent, Role::Admin)) {
            return true;
        }

        // Une inspection concerne le partenaire du véhicule ; un état des lieux, aussi le client
        return $this->demande
            ? $this->demande->client_id === $user->id || $this->demande->partenaire->user_id === $user->id
            : $this->vehicule->partenaire->user_id === $user->id;
    }
}
