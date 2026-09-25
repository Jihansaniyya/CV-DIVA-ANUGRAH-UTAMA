<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_pengguna_dapat_login_dengan_username_dan_password(): void
    {
        $this->userDenganPeran(RoleCode::ADMIN, ['username' => 'admin', 'password' => 'password123']);

        $response = $this->postJson('/api/login', ['username' => 'admin', 'password' => 'password123']);

        $response->assertOk()
            ->assertJsonStructure(['message', 'token', 'user' => ['id', 'name', 'username', 'role_code']])
            ->assertJsonPath('user.role_code', 'ADMIN');
    }

    public function test_login_ditolak_saat_password_salah(): void
    {
        $this->userDenganPeran(RoleCode::QS, ['username' => 'qs.nisa', 'password' => 'password123']);

        $this->postJson('/api/login', ['username' => 'qs.nisa', 'password' => 'salah-sekali'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $this->userDenganPeran(RoleCode::QS, ['username' => 'qs.nonaktif', 'password' => 'password123', 'is_active' => false]);

        $this->postJson('/api/login', ['username' => 'qs.nonaktif', 'password' => 'password123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');
    }

    public function test_endpoint_terproteksi_menolak_permintaan_tanpa_token(): void
    {
        $this->getJson('/api/projects')->assertStatus(401);
    }

    public function test_pengguna_dapat_melihat_profil_dan_logout(): void
    {
        $user = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $this->actingAs($user)->postJson('/api/logout')->assertOk();
    }
}
