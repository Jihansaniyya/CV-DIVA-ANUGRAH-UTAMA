<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_hanya_admin_yang_dapat_mengelola_pengguna(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $qs = $this->userDenganPeran(RoleCode::QS);
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $this->actingAs($admin)->getJson('/api/users')->assertOk();
        $this->actingAs($qs)->getJson('/api/users')->assertStatus(403);
        $this->actingAs($kontraktor)->getJson('/api/users')->assertStatus(403);
    }

    public function test_qs_dan_kontraktor_tidak_dapat_membuat_proyek(): void
    {
        $qs = $this->userDenganPeran(RoleCode::QS);
        $payload = [
            'nama_proyek' => 'Proyek Uji',
            'lokasi' => 'Bontang',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addWeeks(4)->toDateString(),
        ];

        $this->actingAs($qs)->postJson('/api/projects', $payload)->assertStatus(403);
        $this->actingAs($this->userDenganPeran(RoleCode::KONTRAKTOR))->postJson('/api/projects', $payload)->assertStatus(403);
    }

    public function test_qs_hanya_melihat_proyek_yang_ditugaskan_kepadanya(): void
    {
        ['project' => $proyekSaya, 'qs' => $qs] = $this->proyekContoh();
        $proyekLain = Project::factory()->create();

        $response = $this->actingAs($qs)->getJson('/api/projects')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($proyekSaya->id));
        $this->assertFalse($ids->contains($proyekLain->id));
    }

    public function test_qs_tidak_dapat_membuka_detail_proyek_milik_qs_lain(): void
    {
        ['qs' => $qs] = $this->proyekContoh();
        $proyekLain = Project::factory()->create();

        $this->actingAs($qs)->getJson("/api/projects/{$proyekLain->id}")->assertStatus(403);
    }

    public function test_kontraktor_tidak_dapat_menginput_progres(): void
    {
        ['project' => $project, 'items' => $items] = $this->proyekContoh();
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $this->actingAs($kontraktor)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => now()->toDateString(),
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 10]],
        ])->assertStatus(403);
    }

    public function test_akun_nonaktif_ditolak_pada_seluruh_endpoint(): void
    {
        $user = $this->userDenganPeran(RoleCode::ADMIN, ['is_active' => false]);

        $this->actingAs($user)->getJson('/api/projects')->assertStatus(403);
    }
}
