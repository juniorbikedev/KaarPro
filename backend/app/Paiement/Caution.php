<?php

namespace App\Paiement;

use App\Models\Paiement;
use App\Notifications\Alerte;

/** Décision sur la caution d'une location : restitution entière, ou retenue motivée pour des dommages constatés. */
class Caution
{
    public function statuer(Paiement $paiement, int $retenue, ?string $motif): Paiement
    {
        $paiement->update([
            'statut_caution' => Paiement::CAUTION_RESTITUEE,
            'caution_retenue' => $retenue,
            'motif_retenue' => $retenue > 0 ? $motif : null,
            'caution_traitee_le' => now(),
        ]);

        $rendu = number_format($paiement->montant_caution - $retenue, 0, ',', ' ');
        $detail = $retenue > 0 ? ' (retenue de '.number_format($retenue, 0, ',', ' ')." FCFA : {$motif})" : '';
        $paiement->client->notify(new Alerte('caution', 'Caution restituée',
            "{$rendu} FCFA de votre caution vous sont restitués{$detail}.", '/demandes'));

        return $paiement;
    }
}
