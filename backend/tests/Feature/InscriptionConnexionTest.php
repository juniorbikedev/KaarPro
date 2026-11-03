<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InscriptionConnexionTest extends TestCase
{
    use RefreshDatabase;

    private function compte(array $extra = []): array
    {
        return [
            'nom' => 'Awa Diop',
            'email' => 'awa@example.sn',
            'telephone' => '77 123 45 67',
            'password' => 'motdepasse-test',
            'password_confirmation' => 'motdepasse-test',
            ...$extra,
        ];
    }

    public function test_un_client_cree_son_compte_et_recoit_un_jeton(): void
    {
        $this->postJson('/api/auth/inscription/client', $this->compte())
            ->assertCreated()
            ->assertJsonPath('utilisateur.role', 'client')
            ->assertJsonStructure(['token', 'utilisateur' => ['id', 'nom', 'email']]);

        $this->assertDatabaseHas('users', ['email' => 'awa@example.sn', 'role' => 'client']);
    }

    public function test_l_inscription_refuse_un_email_deja_utilise(): void
    {
        User::factory()->create(['email' => 'awa@example.sn']);

        $this->postJson('/api/auth/inscription/client', $this->compte())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_un_partenaire_s_inscrit_avec_son_type_d_activite_et_sa_piece_d_identite(): void
    {
        Storage::fake('local');

        $reponse = $this->post('/api/auth/inscription/partenaire', $this->compte([
            'nom_commercial' => 'Teranga Location',
            'type_activite' => 'location',
            'piece_identite' => UploadedFile::fake()->create('cni.pdf', 200, 'application/pdf'),
        ]), ['Accept' => 'application/json']);

        $reponse->assertCreated()
            ->assertJsonPath('utilisateur.role', 'vendeur')
            ->assertJsonPath('utilisateur.partenaire.type_activite', 'location')
            ->assertJsonPath('utilisateur.partenaire.peut_vendre', false)
            ->assertJsonPath('utilisateur.partenaire.statut_validation', 'en_attente');

        $user = User::where('email', 'awa@example.sn')->first();
        $document = $user->partenaire->documents()->first();
        $this->assertSame('piece_identite', $document->type->value);
        Storage::disk('local')->assertExists($document->chemin);
    }

    public function test_l_inscription_partenaire_exige_la_piece_d_identite(): void
    {
        $this->postJson('/api/auth/inscription/partenaire', $this->compte([
            'nom_commercial' => 'Teranga Location',
            'type_activite' => 'vente',
        ]))->assertUnprocessable()->assertJsonValidationErrors('piece_identite');
    }

    public function test_connexion_avec_les_bons_identifiants(): void
    {
        User::factory()->create(['email' => 'awa@example.sn', 'password' => 'motdepasse-test']);

        $this->postJson('/api/auth/connexion', ['email' => 'awa@example.sn', 'password' => 'motdepasse-test'])
            ->assertOk()
            ->assertJsonStructure(['token', 'utilisateur']);
    }

    public function test_connexion_refusee_avec_un_mauvais_mot_de_passe(): void
    {
        User::factory()->create(['email' => 'awa@example.sn', 'password' => 'motdepasse-test']);

        $this->postJson('/api/auth/connexion', ['email' => 'awa@example.sn', 'password' => 'faux'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        User::factory()->inactif()->create(['email' => 'awa@example.sn', 'password' => 'motdepasse-test']);

        $this->postJson('/api/auth/connexion', ['email' => 'awa@example.sn', 'password' => 'motdepasse-test'])
            ->assertForbidden();
    }

    public function test_un_utilisateur_connecte_consulte_et_modifie_son_profil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/moi', ['nom' => 'Nouveau nom', 'telephone' => '78 000 00 00'])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau nom');
    }

    public function test_la_deconnexion_supprime_le_jeton(): void
    {
        $user = User::factory()->create();
        $jeton = $user->createToken('kaarpro')->plainTextToken;

        $this->withToken($jeton)->postJson('/api/auth/deconnexion')->assertOk();
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_un_partenaire_change_son_type_d_activite_mais_pas_un_client(): void
    {
        $partenaire = \App\Models\Partenaire::factory()->create();

        $this->actingAs($partenaire->user)->putJson('/api/moi/type-activite', ['type_activite' => 'vente'])
            ->assertOk()
            ->assertJsonPath('data.partenaire.type_activite', 'vente');

        $client = User::factory()->role(Role::Client)->create();
        $this->actingAs($client)->putJson('/api/moi/type-activite', ['type_activite' => 'vente'])
            ->assertForbidden();
    }
}
