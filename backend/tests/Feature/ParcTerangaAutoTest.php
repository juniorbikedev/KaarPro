<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutValidation;
use App\Enums\TypeActivite;
use App\Models\Demande;
use App\Models\Partenaire;
use App\Models\Signalement;
use App\Models\User;
use App\Models\Vehicule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Parc de TerangaAuto : l'administrateur publie et gère les véhicules de l'entreprise. */
class ParcTerangaAutoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['kaarpro.commission.location' => 10, 'kaarpro.commission.vente' => 3]);
    }

    private function admin(): User
    {
        return User::factory()->role(Role::Admin)->create();
    }

    private function annonce(array $extra = []): array
    {
        return [
            'immatriculation' => 'dk-7777-ta',
            'marque' => 'Hyundai',
            'modele' => 'Tucson',
            'annee' => 2023,
            'categorie' => 'suv',
            'carburant' => 'diesel',
            'boite' => 'automatique',
            'places' => 5,
            'kilometrage' => 12000,
            'ville' => 'Dakar',
            'mode_offre' => 'vente_et_location',
            'prix_jour' => 45000,
            'prix_vente' => 18_000_000,
            'montant_caution' => 200000,
            'photos' => [UploadedFile::fake()->image('avant.jpg')],
            'carte_grise' => UploadedFile::fake()->create('carte-grise.pdf', 50, 'application/pdf'),
            'assurance' => UploadedFile::fake()->create('assurance.pdf', 50, 'application/pdf'),
            ...$extra,
        ];
    }

    public function test_l_administrateur_publie_un_vehicule_du_parc(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/api/admin/parc/vehicules', $this->annonce(), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.immatriculation', 'DK-7777-TA')
            ->assertJsonPath('data.a_louer', true)
            ->assertJsonPath('data.a_vendre', true);

        $interne = Partenaire::where('est_interne', true)->sole();
        $this->assertSame('TerangaAuto', $interne->nom_commercial);
        $this->assertSame(StatutValidation::Valide, $interne->statut_validation);
        $this->assertSame($interne->id, Vehicule::first()->partenaire_id);

        $this->actingAs($admin)->getJson('/api/admin/parc/vehicules')->assertOk()->assertJsonCount(1, 'data');

        // Le véhicule apparaît au catalogue avec la marque TerangaAuto
        $this->getJson('/api/vehicules/'.Vehicule::first()->id)
            ->assertOk()
            ->assertJsonPath('data.partenaire.nom_commercial', 'TerangaAuto')
            ->assertJsonPath('data.partenaire.est_interne', true);
    }

    public function test_un_seul_parc_est_partage_par_les_administrateurs(): void
    {
        $this->actingAs($this->admin())->post('/api/admin/parc/vehicules', $this->annonce(), ['Accept' => 'application/json'])->assertCreated();

        $this->actingAs($this->admin())->getJson('/api/admin/parc/vehicules')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(1, Partenaire::where('est_interne', true)->count());
    }

    public function test_seul_l_administrateur_accede_au_parc(): void
    {
        $vendeur = Partenaire::factory()->valide()->create()->user;
        $client = User::factory()->create();

        $this->actingAs($vendeur)->getJson('/api/admin/parc/vehicules')->assertForbidden();
        $this->actingAs($client)->getJson('/api/admin/parc/vehicules')->assertForbidden();
        $this->assertSame(0, Partenaire::where('est_interne', true)->count());
    }

    public function test_le_parc_n_apparait_pas_parmi_les_partenaires_a_valider(): void
    {
        $admin = $this->admin();
        Partenaire::interne($admin);
        Partenaire::factory()->create();

        $this->actingAs($admin)->getJson('/api/admin/partenaires')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.est_interne', false);
    }

    public function test_aucune_commission_sur_un_vehicule_du_parc(): void
    {
        $admin = $this->admin();
        $interne = Partenaire::interne($admin);
        $vehicule = Vehicule::factory()->create(['partenaire_id' => $interne->id]);
        $demande = Demande::factory()->create(['vehicule_id' => $vehicule->id, 'montant' => 90_000, 'montant_caution' => 100_000]);

        // L'administrateur traite la demande depuis le parc
        $this->actingAs($admin)->getJson('/api/admin/parc/demandes')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($admin)->postJson("/api/admin/parc/demandes/{$demande->id}/accepter")->assertOk();
        $contrat = $demande->refresh()->contrat;
        $this->actingAs($demande->client)->postJson("/api/contrats/{$contrat->id}/signer", ['accepte' => true])->assertOk();

        $this->actingAs($demande->client)->postJson("/api/demandes/{$demande->id}/paiements", [
            'operateur' => 'wave',
            'telephone' => '77 123 45 67',
        ])
            ->assertCreated()
            ->assertJsonPath('data.commission', 0)
            ->assertJsonPath('data.montant_partenaire', 90_000);

        // Le contrat porte les coordonnées de TerangaAuto, pas celles de l'administrateur qui l'a accepté
        $this->assertSame('Service commercial TerangaAuto', $contrat->donnees['partenaire']['representant']);
        $this->assertSame(config('kaarpro.parc.telephone'), $contrat->donnees['partenaire']['telephone']);
    }

    public function test_le_compte_qui_porte_le_parc_ne_peut_pas_etre_desactive(): void
    {
        $proprietaire = $this->admin();
        $autre = $this->admin();
        $vehicule = Vehicule::factory()->create(['partenaire_id' => Partenaire::interne($proprietaire)->id]);
        $this->assertTrue($proprietaire->estCompteDuParc());

        // Ni depuis la page des utilisateurs…
        $this->actingAs($autre)->patchJson("/api/admin/utilisateurs/{$proprietaire->id}/activation", ['est_actif' => false])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'parc TerangaAuto'));
        // … ni à la suite d'un signalement
        $client = User::factory()->create();
        $this->actingAs($client)->postJson('/api/signalements', [
            'utilisateur_id' => $proprietaire->id, 'motif' => 'comportement', 'description' => 'Test de signalement du parc.',
        ])->assertCreated();
        $signalement = Signalement::where('utilisateur_id', $proprietaire->id)->sole()->id;
        $this->actingAs($autre)->postJson("/api/admin/signalements/{$signalement}/traiter", [
            'statut' => 'traite', 'decision' => 'Essai', 'mesure' => 'desactiver_compte',
        ])->assertStatus(422);
        $this->assertTrue($proprietaire->refresh()->est_actif);
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertOk();

        // Il reste un administrateur comme les autres dans la liste, et un autre administrateur peut être désactivé
        $this->actingAs($autre)->getJson('/api/admin/utilisateurs')->assertOk()->assertJsonFragment(['email' => $proprietaire->email]);
        $this->actingAs($proprietaire)->patchJson("/api/admin/utilisateurs/{$autre->id}/activation", ['est_actif' => false])->assertOk();
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertOk();
    }

    public function test_les_annonces_d_un_compte_desactive_disparaissent_du_catalogue(): void
    {
        $partenaire = Partenaire::factory()->valide()->create();
        $vehicule = Vehicule::factory()->create(['partenaire_id' => $partenaire->id]);
        $client = User::factory()->create();
        $this->getJson('/api/vehicules')->assertJsonCount(1, 'data');

        $this->actingAs($this->admin())->patchJson("/api/admin/utilisateurs/{$partenaire->user_id}/activation", ['est_actif' => false])->assertOk();

        $this->getJson('/api/vehicules')->assertJsonCount(0, 'data');
        $this->getJson("/api/vehicules/{$vehicule->id}")->assertNotFound();
        $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/demandes", ['type' => 'achat'])->assertNotFound();
    }

    public function test_les_vehicules_de_demonstration_ont_leurs_documents(): void
    {
        $this->seed();

        $vehicules = Vehicule::withCount('documents')->get();
        $this->assertGreaterThanOrEqual(12, $vehicules->count());
        $this->assertTrue($vehicules->every(fn ($v) => $v->documents_count === 2));
        $this->assertSame(6, Vehicule::whereHas('partenaire', fn ($q) => $q->where('est_interne', true))->count());
        Storage::disk('local')->assertExists($vehicules->first()->documents->first()->chemin);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($vehicules->first()->documents->first()->chemin));
    }

    public function test_tous_les_administrateurs_sont_notifies_et_partagent_les_messages_du_parc(): void
    {
        $premier = $this->admin();
        $second = $this->admin();
        $vehicule = Vehicule::factory()->create(['partenaire_id' => Partenaire::interne($premier)->id]);
        $client = User::factory()->create();

        $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/demandes", ['type' => 'achat'])->assertCreated();

        foreach ([$premier, $second] as $admin) {
            $notification = $admin->notifications()->sole();
            $this->assertSame('/admin/parc/demandes', $notification->data['lien']);
        }

        $conversation = $this->actingAs($client)->postJson("/api/vehicules/{$vehicule->id}/conversation")->json('data.id');
        $this->actingAs($client)->postJson("/api/conversations/{$conversation}/messages", ['contenu' => 'Est-il toujours disponible ?'])->assertOk();

        $this->actingAs($second)->getJson('/api/conversations')
            ->assertOk()
            ->assertJsonPath('data.0.non_lus', 1)
            ->assertJsonPath('data.0.interlocuteur', $client->nom);
        $this->actingAs($second)->postJson("/api/conversations/{$conversation}/messages", ['contenu' => 'Oui, passez au showroom.'])->assertOk();

        $this->actingAs($premier)->getJson("/api/conversations/{$conversation}")
            ->assertOk()
            ->assertJsonPath('data.messages.1.de_moi', true)
            ->assertJsonPath('data.messages.0.de_moi', false);
        $this->actingAs($client)->getJson("/api/conversations/{$conversation}")->assertJsonPath('data.messages.1.de_moi', false);

        // Un vendeur ne voit pas les conversations du parc
        $vendeur = Partenaire::factory()->valide()->create()->user;
        $this->actingAs($vendeur)->getJson("/api/conversations/{$conversation}")->assertNotFound();
    }

    public function test_l_espace_du_vendeur_reste_le_sien(): void
    {
        $admin = $this->admin();
        Vehicule::factory()->create(['partenaire_id' => Partenaire::interne($this->admin())->id]);
        $partenaire = Partenaire::factory()->valide()->create(['type_activite' => TypeActivite::VenteEtLocation]);

        $this->actingAs($partenaire->user)->getJson('/api/partenaire/vehicules')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($partenaire->user)->post('/api/partenaire/vehicules', $this->annonce(), ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame($partenaire->id, Vehicule::where('immatriculation', 'DK-7777-TA')->value('partenaire_id'));
    }
}
