<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Les trois mesures retenues : mots de passe hachés, accès selon le rôle, HTTPS (avec en-têtes de sécurité). */
class SecuriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_mot_de_passe_est_stocke_hache(): void
    {
        $this->postJson('/api/auth/inscription/client', [
            'nom' => 'Fatou Sow', 'email' => 'fatou@example.test', 'telephone' => '770000000',
            'password' => 'motdepasse-solide', 'password_confirmation' => 'motdepasse-solide',
        ])->assertCreated()->assertJsonMissingPath('data.password');

        $hache = User::where('email', 'fatou@example.test')->value('password');
        $this->assertNotSame('motdepasse-solide', $hache);
        $this->assertTrue(Hash::check('motdepasse-solide', $hache));
    }

    /** Chaque espace est fermé aux autres rôles et aux visiteurs. */
    public function test_chaque_espace_est_reserve_a_son_role(): void
    {
        $routes = [
            '/api/admin/tableau-de-bord' => Role::Admin,
            '/api/admin/paiements' => Role::Admin,
            '/api/agent/missions' => Role::Agent,
            '/api/demandes' => Role::Client,
        ];

        foreach ($routes as $route => $roleAutorise) {
            $this->getJson($route)->assertUnauthorized();

            foreach (Role::cases() as $role) {
                if ($role === $roleAutorise || $role === Role::Vendeur) {
                    continue;
                }
                $this->actingAs(User::factory()->role($role)->create())->getJson($route)->assertForbidden();
            }
            $this->actingAs(User::factory()->role($roleAutorise)->create())->getJson($route)->assertOk();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_les_reponses_portent_les_en_tetes_de_securite(): void
    {
        $this->getJson('/api/vehicules')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeaderMissing('Strict-Transport-Security');

        // Derrière le proxy HTTPS, HSTS impose le chiffrement au navigateur
        $this->getJson('/api/vehicules', ['X-Forwarded-Proto' => 'https'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_un_jeton_expire_est_refuse(): void
    {
        $user = User::factory()->role(Role::Client)->create();
        $jeton = $user->createToken('kaarpro')->plainTextToken;

        $this->getJson('/api/moi', ['Authorization' => "Bearer {$jeton}"])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->travel(config('sanctum.expiration') + 1)->minutes();
        $this->getJson('/api/moi', ['Authorization' => "Bearer {$jeton}"])->assertUnauthorized();
    }

    public function test_la_connexion_est_limitee_contre_les_essais_repetes(): void
    {
        $user = User::factory()->role(Role::Client)->create();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/connexion', ['email' => $user->email, 'password' => 'mauvais'])->assertStatus(422);
        }
        $this->postJson('/api/auth/connexion', ['email' => $user->email, 'password' => 'mauvais'])->assertStatus(429);
    }
}
