<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_dashboard_admin_menampilkan_ringkasan_proyek_dan_pengguna(): void
    {
        $this->proyekContoh();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        $data = $this->actingAs($admin)->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame('ADMIN', $data['peran']);
        $this->assertSame(1, $data['kpi']['total_proyek']);
        $this->assertSame(2, $data['kpi']['total_pekerjaan']);
        $this->assertArrayHasKey('status_proyek', $data);
    }

    public function test_dashboard_qs_hanya_menampilkan_proyek_yang_ditugaskan(): void
    {
        $konteks = $this->proyekContoh();
        $this->proyekContoh();

        $data = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame('QS', $data['peran']);
        $this->assertSame(1, $data['kpi']['total_proyek']);
        $this->assertSame($konteks['project']->id, $data['proyek_ditugaskan'][0]['id']);
    }

    public function test_dashboard_kontraktor_menampilkan_rencana_aktual_dan_deviasi(): void
    {
        $this->proyekContoh();
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $data = $this->actingAs($kontraktor)->getJson('/api/dashboard')->assertOk()->json('data');

        $this->assertSame('KONTRAKTOR', $data['peran']);
        $this->assertArrayHasKey('progres_rencana', $data['kpi']);
        $this->assertArrayHasKey('progres_aktual', $data['kpi']);
        $this->assertArrayHasKey('deviasi', $data['kpi']);
    }
}
