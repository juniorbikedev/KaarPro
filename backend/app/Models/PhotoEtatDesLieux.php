<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoEtatDesLieux extends Model
{
    protected $table = 'photos_etat_des_lieux';

    protected $fillable = ['chemin'];

    public function etatDesLieux(): BelongsTo
    {
        return $this->belongsTo(EtatDesLieux::class);
    }
}
