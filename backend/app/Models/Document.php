<?php

namespace App\Models;

use App\Enums\TypeDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Document extends Model
{
    protected $fillable = [
        'type',
        'chemin',
        'nom_original',
        'est_verifie',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeDocument::class,
            'est_verifie' => 'boolean',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }
}
