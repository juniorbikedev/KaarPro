<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Demande;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_etape_d_une_demande_notifie_l_autre_partie(): void
    {
        $vehicule = Vehicule::factory()->location()->create();
        $partenaire = $vehicule->partenaire->user;
        $client = User::factory()->role(Role::Client)->create();

        $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/demandes", [
            'type' => 'location', 'date_debut' => now()->addDays(2)->toDateString(), 'date_fin' => now()->addDays(3)->toDateString(),
        ])->assertCreated();

        $this->actingAs($partenaire)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('non_lues', 1)
            ->assertJsonPath('data.0.titre', 'Nouvelle demande de location')
            ->assertJsonPath('data.0.lien', '/partenaire/demandes');

        $demande = Demande::sole();
        $this->postJson("/api/partenaire/demandes/{$demande->id}/accepter")->assertOk();

        $this->actingAs($client)->getJson('/api/notifications')
            ->assertJsonPath('data.0.titre', 'Demande acceptée')
            ->assertJsonPath('data.0.lien', "/contrats/{$demande->contrat->id}");

        $this->travel(1)->minute(); // les notifications sont triées à la seconde près
        $this->postJson("/api/contrats/{$demande->contrat->id}/signer", ['accepte' => true]);
        $this->actingAs($partenaire)->getJson('/api/notifications')
            ->assertJsonPath('non_lues', 2)
            ->assertJsonPath('data.0.titre', 'Contrat signé');
    }

    public function test_marquer_une_ou_toutes_les_notifications_comme_lues(): void
    {
        $demande = Demande::factory()->create();
        $client = $demande->client;
        $this->actingAs($demande->partenaire->user)->postJson("/api/partenaire/demandes/{$demande->id}/refuser", ['motif' => 'Indisponible']);

        $id = $this->actingAs($client)->getJson('/api/notifications')->assertJsonPath('non_lues', 1)->json('data.0.id');
        $this->postJson("/api/notifications/{$id}/lire")->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('non_lues', 0)->assertJsonPath('data.0.lu', true);

        // Un utilisateur ne lit pas les notifications d'un autre
        $this->actingAs($demande->partenaire->user)->postJson("/api/notifications/{$id}/lire")->assertNotFound();

        $this->actingAs($client)->postJson('/api/notifications/tout-lire')->assertOk();
    }

    public function test_un_seul_avis_tant_que_les_messages_ne_sont_pas_lus(): void
    {
        $vehicule = Vehicule::factory()->create();
        $client = User::factory()->role(Role::Client)->create();
        $id = $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/conversation")->json('data.id');

        $this->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Bonjour']);
        $this->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Êtes-vous disponible ?']);
        $this->assertSame(1, $vehicule->partenaire->user->notifications()->count());

        // Après lecture, un nouveau message notifie de nouveau
        $this->actingAs($vehicule->partenaire->user)->getJson("/api/conversations/{$id}");
        $this->actingAs($client)->postJson("/api/conversations/{$id}/messages", ['contenu' => 'Merci']);
        $this->assertSame(2, $vehicule->partenaire->user->notifications()->count());
    }
}
