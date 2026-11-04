<?php

namespace Tests\Feature;

use App\Enums\StatutDemande;
use App\Enums\StatutPaiement;
use App\Models\Demande;
use App\Models\Paiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Passerelle PayTech : les appels à paytech.sn sont simulés, aucun paiement réel n'est envoyé. */
class PaytechTest extends TestCase
{
    use RefreshDatabase;

    private const CLE = 'cle-api-de-test';
    private const SECRET = 'cle-secrete-de-test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'kaarpro.paiement.passerelle' => 'paytech',
            'kaarpro.paytech.cle_api' => self::CLE,
            'kaarpro.paytech.cle_secrete' => self::SECRET,
            'kaarpro.paytech.environnement' => 'prod',
            'kaarpro.frontend_url' => 'https://kaarpro.test',
            'app.url' => 'https://kaarpro.test',
        ]);
    }

    private function demandePrete(array $attributs = []): Demande
    {
        $demande = Demande::factory()->create(['montant' => 90_000, 'montant_caution' => 100_000, ...$attributs]);
        $this->actingAs($demande->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();
        $this->actingAs($demande->client)->postJson("/api/contrats/{$demande->refresh()->contrat->id}/signer", ['accepte' => true])->assertOk();

        return $demande->refresh();
    }

    private function fauxPaytech(): void
    {
        Http::fake(['paytech.sn/api/payment/request-payment' => Http::response([
            'success' => 1,
            'token' => 'jeton123',
            'redirect_url' => 'https://paytech.sn/payment/checkout/jeton123',
        ])]);
    }

    private function ipn(Paiement $paiement, array $extra = []): array
    {
        return [
            'type_event' => 'sale_complete',
            'ref_command' => $paiement->reference,
            'item_price' => $paiement->montant_total,
            'final_item_price' => $paiement->montant_total,
            'token' => 'jeton123',
            'payment_method' => 'Wave',
            'env' => 'prod',
            'api_key_sha256' => hash('sha256', self::CLE),
            'api_secret_sha256' => hash('sha256', self::SECRET),
            ...$extra,
        ];
    }

    public function test_la_demande_de_paiement_est_envoyee_a_paytech(): void
    {
        $this->fauxPaytech();
        $demande = $this->demandePrete();

        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertCreated()
            ->assertJsonPath('url_paiement', 'https://paytech.sn/payment/checkout/jeton123')
            ->assertJsonPath('data.simulation', false);

        $paiement = Paiement::sole();
        $this->assertSame('jeton123', $paiement->reference_operateur);

        Http::assertSent(fn (Request $requete) => $requete->url() === 'https://paytech.sn/api/payment/request-payment'
            && $requete->hasHeader('API_KEY', self::CLE)
            && $requete->hasHeader('API_SECRET', self::SECRET)
            && $requete['item_price'] === 190_000
            && $requete['currency'] === 'XOF'
            && $requete['ref_command'] === $paiement->reference
            && $requete['target_payment'] === 'Wave'
            && $requete['env'] === 'prod'
            && $requete['ipn_url'] === 'https://kaarpro.test/api/webhooks/paytech'
            && $requete['success_url'] === "https://kaarpro.test/paiement/{$paiement->reference}");
    }

    public function test_un_refus_de_paytech_marque_la_tentative_echouee(): void
    {
        Http::fake(['paytech.sn/*' => Http::response(['success' => -1, 'message' => 'clé api invalide'])]);
        $demande = $this->demandePrete();

        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'orange_money', 'telephone' => '781234567'])
            ->assertStatus(502);

        $this->assertSame(StatutPaiement::Echoue, Paiement::sole()->statut);
        $this->assertSame(StatutDemande::Acceptee, $demande->refresh()->statut);
    }

    public function test_l_ipn_authentique_confirme_le_paiement(): void
    {
        $this->fauxPaytech();
        $demande = $this->demandePrete();
        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567']);
        $paiement = Paiement::sole();

        // PayTech envoie un formulaire, sans jeton d'accès
        $this->app['auth']->forgetGuards();
        $this->post('/api/webhooks/paytech', $this->ipn($paiement))->assertOk()->assertSee('IPN OK');

        $this->assertSame(StatutPaiement::Reussi, $paiement->refresh()->statut);
        $this->assertSame(StatutDemande::Payee, $demande->refresh()->statut);
        $this->assertSame(2, $demande->etatsDesLieux()->count());

        // Une seconde notification identique ne change rien
        $this->post('/api/webhooks/paytech', $this->ipn($paiement))->assertOk();
        $this->assertSame(2, $demande->etatsDesLieux()->count());
    }

    public function test_l_ipn_signee_par_hmac_est_acceptee(): void
    {
        $this->fauxPaytech();
        $demande = $this->demandePrete();
        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567']);
        $paiement = Paiement::sole();
        $hmac = hash_hmac('sha256', "{$paiement->montant_total}|{$paiement->reference}|".self::CLE, self::SECRET);

        $this->app['auth']->forgetGuards();
        $this->post('/api/webhooks/paytech', $this->ipn($paiement, ['hmac_compute' => $hmac, 'api_key_sha256' => '', 'api_secret_sha256' => '']))
            ->assertOk();

        $this->assertSame(StatutPaiement::Reussi, $paiement->refresh()->statut);
    }

    public function test_une_ipn_falsifiee_ou_incomplete_est_refusee(): void
    {
        $this->fauxPaytech();
        $demande = $this->demandePrete();
        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567']);
        $paiement = Paiement::sole();
        $this->app['auth']->forgetGuards();

        $this->post('/api/webhooks/paytech', $this->ipn($paiement, ['api_secret_sha256' => hash('sha256', 'autre-secret')]))->assertForbidden();
        $this->post('/api/webhooks/paytech', $this->ipn($paiement, ['hmac_compute' => 'faux']))->assertForbidden();
        // Montant payé inférieur à la commande en production
        $this->post('/api/webhooks/paytech', $this->ipn($paiement, ['item_price' => 100, 'final_item_price' => 100]))->assertStatus(422);

        $this->assertSame(StatutPaiement::EnAttente, $paiement->refresh()->statut);
    }

    public function test_un_paiement_annule_chez_paytech_peut_etre_retente(): void
    {
        $this->fauxPaytech();
        $demande = $this->demandePrete();
        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567']);
        $paiement = Paiement::sole();

        $this->app['auth']->forgetGuards();
        $this->post('/api/webhooks/paytech', $this->ipn($paiement, ['type_event' => 'sale_canceled']))->assertOk();

        $this->assertSame(StatutPaiement::Echoue, $paiement->refresh()->statut);
        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'orange_money', 'telephone' => '781234567'])
            ->assertCreated();

        // Le simulateur n'est pas utilisable quand PayTech est la passerelle
        $this->postJson("/api/paiements/{$paiement->reference}/simulateur", ['resultat' => 'reussi'])->assertNotFound();
    }
}
