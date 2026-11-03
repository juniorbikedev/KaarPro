<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contrat extends Model
{
    protected $fillable = ['numero', 'donnees', 'signe_partenaire_le', 'signe_client_le'];

    protected function casts(): array
    {
        return [
            'donnees' => 'array',
            'signe_partenaire_le' => 'datetime',
            'signe_client_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }
}
