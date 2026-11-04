<?php

namespace Tests\Feature;

use App\Enums\EtatVehicule;
use App\Enums\Role;
use App\Enums\StatutDemande;
use App\Models\Demande;
use App\Models\Paiement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaiementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['kaarpro.commission.location' => 10, 'kaarpro.commission.vente' => 3]);
    }

    /** Demande acceptée par le partenaire puis contrat signé par le client, prête à être payée. */
    private function demandePrete(array $attributs = []): Demande
    {
        $demande = Demande::factory()->create($attributs);
        $this->actingAs($demande->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();
        $contrat = $demande->refresh()->contrat;
        $this->actingAs($demande->client)->postJson("/api/contrats/{$contrat->id}/signer", ['accepte' => true])->assertOk();

        return $demande->refresh();
    }

    public function test_paiement_d_une_location_avec_commission_et_caution(): void
    {
        $demande = $this->demandePrete(['montant' => 90_000, 'montant_caution' => 100_000]);

        $reponse = $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", [
            'operateur' => 'wave',
            'telephone' => '77 123 45 67',
        ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'en_attente')
            ->assertJsonPath('data.telephone', '771234567')
            ->assertJsonPath('data.montant_total', 190_000)
            ->assertJsonPath('data.commission', 9_000)
            ->assertJsonPath('data.montant_partenaire', 81_000);

        $reference = $reponse->json('data.reference');
        $this->assertStringEndsWith("/paiement/{$reference}", $reponse->json('url_paiement'));

        $this->postJson("/api/paiements/{$reference}/simulateur", ['resultat' => 'reussi'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'reussi')
            ->assertJsonPath('data.statut_caution', 'bloquee');

        $this->assertSame(StatutDemande::Payee, $demande->refresh()->statut);
        $this->getJson('/api/demandes')->assertJsonPath('data.0.paiement.reference', $reference);

        // Une seconde tentative est refusée : la demande est déjà payée
        $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertStatus(422);
    }

    public function test_le_contrat_doit_etre_signe_et_le_numero_valide(): void
    {
        $demande = Demande::factory()->create();
        $this->actingAs($demande->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();

        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Signez le contrat avant de payer.');

        $this->postJson("/api/contrats/{$demande->refresh()->contrat->id}/signer", ['accepte' => true]);
        $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'free', 'telephone' => '661234567'])
            ->assertJsonValidationErrors(['operateur', 'telephone']);
    }

    public function test_un_paiement_echoue_peut_etre_retente(): void
    {
        $demande = $this->demandePrete();

        $premiere = $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'orange_money', 'telephone' => '+221781234567'])
            ->json('data.reference');
        $this->postJson("/api/paiements/{$premiere}/simulateur", ['resultat' => 'echoue'])->assertJsonPath('data.statut', 'echoue');
        $this->assertSame(StatutDemande::Acceptee, $demande->refresh()->statut);

        $seconde = $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '761234567'])
            ->assertCreated()
            ->json('data.reference');
        $this->postJson("/api/paiements/{$seconde}/simulateur", ['resultat' => 'reussi'])->assertJsonPath('data.statut', 'reussi');

        // Une confirmation en double reste sans effet
        $this->postJson("/api/paiements/{$premiere}/simulateur", ['resultat' => 'reussi'])->assertJsonPath('data.statut', 'echoue');
    }

    public function test_une_vente_payee_marque_le_vehicule_vendu_et_clot_les_autres_demandes(): void
    {
        $vehicule = Vehicule::factory()->create(['prix_vente' => 10_000_000]);
        $demande = $this->demandePrete([
            'vehicule_id' => $vehicule->id,
            'partenaire_id' => $vehicule->partenaire_id,
            'type' => 'achat', 'date_debut' => null, 'date_fin' => null, 'nb_jours' => null,
            'montant' => 10_000_000, 'montant_caution' => null,
        ]);
        $autre = Demande::factory()->achat()->create(['vehicule_id' => $vehicule->id, 'partenaire_id' => $vehicule->partenaire_id]);

        $reference = $this->actingAs($demande->client)
            ->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertJsonPath('data.montant_caution', 0)
            ->assertJsonPath('data.commission', 300_000)
            ->json('data.reference');
        $this->postJson("/api/paiements/{$reference}/simulateur", ['resultat' => 'reussi'])->assertOk();

        $this->assertSame(EtatVehicule::Vendu, $vehicule->refresh()->etat);
        $this->assertSame(StatutDemande::Refusee, $autre->refresh()->statut);
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertNotFound();

        $this->actingAs($vehicule->partenaire->user)->getJson('/api/partenaire/paiements')
            ->assertOk()
            ->assertJsonPath('totaux.montant_partenaire', 9_700_000);
    }

    public function test_seul_le_client_concerne_paie_et_consulte_son_paiement(): void
    {
        $demande = $this->demandePrete();
        $intrus = User::factory()->role(Role::Client)->create();

        $this->actingAs($intrus)->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->assertNotFound();

        $reference = $this->actingAs($demande->client)
            ->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->json('data.reference');
        $this->actingAs($intrus)->getJson("/api/paiements/{$reference}")->assertNotFound();
        $this->postJson("/api/paiements/{$reference}/simulateur", ['resultat' => 'reussi'])->assertNotFound();
    }

    public function test_l_administrateur_restitue_la_caution_avec_une_retenue(): void
    {
        $demande = $this->demandePrete(['montant_caution' => 100_000]);
        $reference = $this->postJson("/api/demandes/{$demande->id}/paiements", ['operateur' => 'wave', 'telephone' => '771234567'])
            ->json('data.reference');
        $this->postJson("/api/paiements/{$reference}/simulateur", ['resultat' => 'reussi']);

        $admin = User::factory()->role(Role::Admin)->create();
        $this->actingAs($admin)->getJson('/api/admin/paiements')
            ->assertOk()
            ->assertJsonPath('totaux.cautions_bloquees', 100_000);

        // La caution ne se restitue qu'après l'état des lieux de retour
        $this->postJson("/api/admin/paiements/{$reference}/caution", ['caution_retenue' => 0])->assertStatus(422);
        $demande->etatsDesLieux()->update(['realise_le' => now()]);

        $this->postJson("/api/admin/paiements/{$reference}/caution", ['caution_retenue' => 25_000])
            ->assertJsonValidationErrors('motif_retenue');
        $this->postJson("/api/admin/paiements/{$reference}/caution", ['caution_retenue' => 150_000, 'motif_retenue' => 'Rétroviseur'])
            ->assertJsonValidationErrors('caution_retenue');

        $this->postJson("/api/admin/paiements/{$reference}/caution", ['caution_retenue' => 25_000, 'motif_retenue' => 'Rétroviseur cassé'])
            ->assertOk()
            ->assertJsonPath('data.statut_caution', 'restituee')
            ->assertJsonPath('data.caution_retenue', 25_000);

        $this->postJson("/api/admin/paiements/{$reference}/caution", ['caution_retenue' => 0])->assertStatus(422);
        $this->assertContains('Caution restituée', $demande->client->notifications->pluck('data.titre'));
        $this->assertSame(Paiement::CAUTION_RESTITUEE, Paiement::first()->statut_caution);
    }
}
