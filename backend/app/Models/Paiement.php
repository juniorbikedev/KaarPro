<?php

namespace App\Models;

use App\Enums\Operateur;
use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    public const CAUTION_BLOQUEE = 'bloquee';
    public const CAUTION_RESTITUEE = 'restituee';

    protected $fillable = [
        'client_id', 'reference', 'operateur', 'telephone', 'montant', 'montant_caution', 'montant_total',
        'taux_commission', 'commission', 'montant_partenaire', 'statut', 'reference_operateur', 'paye_le',
        'statut_caution', 'caution_retenue', 'motif_retenue', 'caution_traitee_le',
    ];

    protected function casts(): array
    {
        return [
            'operateur' => Operateur::class,
            'statut' => StatutPaiement::class,
            'montant' => 'integer',
            'montant_caution' => 'integer',
            'montant_total' => 'integer',
            'taux_commission' => 'float',
            'commission' => 'integer',
            'montant_partenaire' => 'integer',
            'caution_retenue' => 'integer',
            'paye_le' => 'datetime',
            'caution_traitee_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo
    {
        return $this->belongsTo(Demande::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }
}
