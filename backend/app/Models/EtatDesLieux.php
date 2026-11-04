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
        'type', 'agent_id', 'kilometrage', 'niveau_carburant', 'observations', 'dommages', 'montant_dommages', 'realise_le',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeEtatDesLieux::class,
            'kilometrage' => 'integer',
            'montant_dommages' => 'integer',
            'realise_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(PhotoEtatDesLieux::class);
    }

    public function estRealise(): bool
    {
        return $this->realise_le !== null;
    }

    /** Le client, le partenaire, un agent ou l'administrateur peuvent consulter le constat. */
    public function estVisiblePar(User $user): bool
    {
        return $user->aLeRole(Role::Agent, Role::Admin)
            || $this->demande->client_id === $user->id
            || $this->demande->partenaire->user_id === $user->id;
    }
}
