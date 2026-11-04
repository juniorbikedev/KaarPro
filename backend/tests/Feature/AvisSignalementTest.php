<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreeDesTransactions;
use Tests\TestCase;

class AvisSignalementTest extends TestCase
{
    use CreeDesTransactions, RefreshDatabase;

    public function test_le_client_note_apres_le_retour_du_vehicule(): void
    {
        $demande = $this->demandePayee();
        $client = $demande->client;
        $client->update(['nom' => 'Awa Diop']);

        $this->actingAs($client)->postJson("/api/demandes/{$demande->id}/avis", ['note' => 5])
            ->assertStatus(422);

        $demande->etatsDesLieux()->update(['realise_le' => now()]);
        $this->getJson('/api/demandes')->assertJsonPath('data.0.peut_donner_avis', true);

        $this->postJson("/api/demandes/{$demande->id}/avis", ['note' => 6])->assertJsonValidationErrors('note');
        $this->postJson("/api/demandes/{$demande->id}/avis", ['note' => 4, 'commentaire' => 'Véhicule propre, loueur ponctuel.'])
            ->assertCreated()
            ->assertJsonPath('data.auteur', 'Awa D.');
        $this->postJson("/api/demandes/{$demande->id}/avis", ['note' => 5])->assertStatus(422);

        $this->getJson("/api/vehicules/{$demande->vehicule_id}/avis")
            ->assertOk()
            ->assertJsonPath('data.0.note', 4)
            ->assertJsonPath('partenaire.moyenne', 4)
            ->assertJsonPath('partenaire.nombre', 1);
    }

    public function test_l_administrateur_masque_un_avis(): void
    {
        $demande = $this->demandePayee();
        $demande->etatsDesLieux()->update(['realise_le' => now()]);
        $id = $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/avis", ['note' => 1, 'commentaire' => 'Propos injurieux'])->json('data.id');

        $admin = User::factory()->role(Role::Admin)->create();
        $this->actingAs($admin)->postJson("/api/admin/avis/{$id}/masquer", ['est_masque' => true])->assertOk();
        $this->getJson("/api/vehicules/{$demande->vehicule_id}/avis")->assertJsonCount(0, 'data')->assertJsonPath('partenaire.nombre', 0);
    }

    public function test_signaler_une_annonce_puis_la_retirer(): void
    {
        $vehicule = Vehicule::factory()->create();
        $client = User::factory()->role(Role::Client)->create();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($client)->postJson('/api/signalements', ['motif' => 'fraude', 'description' => 'Court'])
            ->assertJsonValidationErrors(['vehicule_id', 'description']);
        $this->postJson('/api/signalements', [
            'vehicule_id' => $vehicule->id, 'motif' => 'annonce_trompeuse', 'description' => 'Les photos ne correspondent pas au véhicule.',
        ])->assertCreated();
        $this->assertSame('Nouveau signalement', $admin->notifications()->first()->data['titre']);

        $id = Signalement::sole()->id;
        $this->actingAs($admin)->getJson('/api/admin/signalements?statut=ouvert')->assertJsonCount(1, 'data');
        $this->postJson("/api/admin/signalements/{$id}/traiter", ['statut' => 'traite', 'decision' => 'Photos non conformes.', 'mesure' => 'retirer_annonce'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'traite')
            ->assertJsonPath('data.cible.retiree', true);

        $this->assertSame(Vehicule::RETIRE, $vehicule->refresh()->statut_publication);
        $this->postJson("/api/admin/signalements/{$id}/traiter", ['statut' => 'rejete', 'decision' => 'x'])->assertStatus(422);
        $this->assertSame('Signalement traité', $client->notifications()->first()->data['titre']);
    }

    public function test_signaler_un_utilisateur_et_desactiver_son_compte(): void
    {
        $vendeur = Vehicule::factory()->create()->partenaire->user;
        $client = User::factory()->role(Role::Client)->create();
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($client)->postJson('/api/signalements', ['utilisateur_id' => $client->id, 'motif' => 'autre', 'description' => 'Je me signale moi-même.'])
            ->assertJsonValidationErrors('utilisateur_id');
        $this->postJson('/api/signalements', ['utilisateur_id' => $vendeur->id, 'motif' => 'comportement', 'description' => 'Demande un paiement hors plateforme.'])
            ->assertCreated();

        $this->actingAs($admin)->postJson('/api/admin/signalements/'.Signalement::sole()->id.'/traiter', [
            'statut' => 'traite', 'decision' => 'Compte suspendu.', 'mesure' => 'desactiver_compte',
        ])->assertOk()->assertJsonPath('data.cible.actif', false);

        $this->assertFalse($vendeur->refresh()->est_actif);
    }

    public function test_le_tableau_de_bord_est_reserve_a_l_administrateur(): void
    {
        $this->demandePayee(['montant' => 100_000]);
        $admin = User::factory()->role(Role::Admin)->create();

        $this->actingAs($admin)->getJson('/api/admin/tableau-de-bord')
            ->assertOk()
            ->assertJsonPath('data.finances.commission_totale', 10_000)
            ->assertJsonPath('data.demandes.payee', 1)
            ->assertJsonPath('data.a_traiter.missions_a_attribuer', 2)
            ->assertJsonCount(6, 'data.commissions_par_mois')
            ->assertJsonPath('data.commissions_par_mois.5.commission', 10_000);

        $agent = User::factory()->role(Role::Agent)->create();
        $this->actingAs($agent)->getJson('/api/admin/tableau-de-bord')->assertForbidden();
    }
}
