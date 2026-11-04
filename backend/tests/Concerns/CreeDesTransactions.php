<?php

namespace Tests\Concerns;

use App\Models\Demande;

/** Fait passer une demande par tout le parcours : acceptation, signature du contrat, paiement simulé. */
trait CreeDesTransactions
{
    protected function demandePayee(array $attributs = []): Demande
    {
        $demande = Demande::factory()->create($attributs);
        $this->actingAs($demande->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();

        $client = $demande->client;
        $this->actingAs($client)->postJson("/api/contrats/{$demande->refresh()->contrat->id}/signer", ['accepte' => true])->assertOk();
        $reference = $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertCreated()
            ->json('data.reference');
        $this->postJson("/api/paiements/{$reference}/simulateur", ['resultat' => 'reussi'])->assertOk();

        return $demande->refresh();
    }
}
