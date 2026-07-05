<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Supervision;
use App\Models\User;
use App\Models\Well;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForageTrackTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        return $admin->createToken('test')->plainTextToken;
    }

    private function userToken()
    {
        $user = User::factory()->create(['role' => 'user']);
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Test 1 : Login avec credentials valides retourne un token
     */
    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::factory()->create([
            'email' => 'test@forage.ne',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@forage.ne',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['user', 'token'])
                 ->assertJsonPath('user.email', 'test@forage.ne');
    }

    /**
     * Test 2 : Login avec mauvais mot de passe retourne 401
     */
    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email' => 'test@forage.ne',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@forage.ne',
            'password' => 'mauvais_mot_de_passe',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test 3 : Liste des puits accessible avec token
     */
    public function test_wells_list_returns_200_with_token(): void
    {
        Well::factory()->count(5)->create();

        $response = $this->withToken($this->adminToken())
                         ->getJson('/api/wells');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'total', 'per_page']);
    }

    /**
     * Test 4 : Liste des puits inaccessible sans token
     */
    public function test_wells_list_returns_401_without_token(): void
    {
        $response = $this->getJson('/api/wells');
        $response->assertStatus(401);
    }

    /**
     * Test 5 : Dashboard retourne les bonnes clés
     */
    public function test_dashboard_returns_correct_structure(): void
    {
        $response = $this->withToken($this->adminToken())
                         ->getJson('/api/dashboard');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'wells' => ['total', 'operational', 'not_working', 'suspended'],
                     'alerts' => ['total_unresolved', 'critical', 'high', 'medium'],
                     'recent_supervisions',
                     'critical_alerts',
                 ]);
    }

    /**
     * Test 6 : Résoudre une alerte fonctionne pour un admin
     */
    public function test_admin_can_resolve_alert(): void
    {
        $well = Well::factory()->create();
        $supervision = Supervision::factory()->create(['well_id' => $well->id]);
        $alert = Alert::factory()->create([
            'well_id' => $well->id,
            'supervision_id' => $supervision->id,
            'resolved' => false,
        ]);

        $response = $this->withToken($this->adminToken())
                         ->patchJson("/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)
                 ->assertJsonPath('resolved', true);

        $this->assertDatabaseHas('alerts', [
            'id' => $alert->id,
            'resolved' => true,
        ]);
    }

    /**
     * Test 7 : Un utilisateur simple ne peut pas résoudre une alerte
     */
    public function test_user_cannot_resolve_alert(): void
    {
        $well = Well::factory()->create();
        $supervision = Supervision::factory()->create(['well_id' => $well->id]);
        $alert = Alert::factory()->create([
            'well_id' => $well->id,
            'supervision_id' => $supervision->id,
            'resolved' => false,
        ]);

        $response = $this->withToken($this->userToken())
                         ->patchJson("/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(403);
    }
}