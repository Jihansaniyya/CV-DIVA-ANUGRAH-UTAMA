<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\Unit;
use App\Models\WorkItem;
use App\Services\ProjectScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_admin_dapat_membuat_proyek_dan_periode_mingguan_terbentuk_otomatis(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $qs = $this->userDenganPeran(RoleCode::QS);

        $response = $this->actingAs($admin)->postJson('/api/projects', [
            'nama_proyek' => 'Pembuatan Penutup Parit RT 09',
            'nomor_spk' => 'SPK/98.1/2025',
            'lokasi' => 'RT. 09 Kel. Bontang Baru',
            'sumber_dana' => 'PAD Kota Bontang',
            'tahun_anggaran' => 2025,
            'tanggal_mulai' => '2025-02-20',
            'tanggal_selesai' => '2025-04-02',
            'jangka_waktu_hari' => 45,
            'qs_user_id' => $qs->id,
        ]);

        $response->assertCreated()->assertJsonPath('data.nama_proyek', 'Pembuatan Penutup Parit RT 09');

        $project = Project::firstOrFail();

        $this->assertSame(6, $project->periods()->count());
        $this->assertSame(45, $project->jangka_waktu_hari);
        $this->assertSame('M-I', $project->periods()->orderBy('urutan')->first()->nama_periode);
        $this->assertDatabaseHas('project_assignments', ['project_id' => $project->id, 'user_id' => $qs->id, 'peran' => 'QS']);
    }

    public function test_validasi_menolak_tanggal_selesai_sebelum_tanggal_mulai(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        $this->actingAs($admin)->postJson('/api/projects', [
            'nama_proyek' => 'Proyek Salah Tanggal',
            'lokasi' => 'Bontang',
            'tanggal_mulai' => '2025-05-10',
            'tanggal_selesai' => '2025-05-01',
        ])->assertStatus(422)->assertJsonValidationErrors('tanggal_selesai');
    }

    public function test_admin_dapat_memperbarui_dan_menghapus_proyek(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $project = Project::factory()->create();

        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", ['lokasi' => 'Kel. Api-Api'])
            ->assertOk()
            ->assertJsonPath('data.lokasi', 'Kel. Api-Api');

        $this->actingAs($admin)->deleteJson("/api/projects/{$project->id}")->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_bobot_pekerjaan_dihitung_dari_harga_pekerjaan(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $project = Project::factory()->create();
        app(ProjectScheduleService::class)->generateWeeklyPeriods($project);
        $rentang = ['period_mulai_id' => $project->periods()->min('id'), 'period_selesai_id' => $project->periods()->max('id')];
        $unit = Unit::where('code', 'm3')->firstOrFail();

        // 100 x 100.000 = 10.000.000 dan 200 x 150.000 = 30.000.000 -> total 40.000.000
        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit->id,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 100,
            'harga_satuan' => 100000,
            ...$rentang,
        ])->assertCreated();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit->id,
            'uraian_pekerjaan' => 'Pembesian',
            'volume' => 200,
            'harga_satuan' => 150000,
            ...$rentang,
        ])->assertCreated();

        $galian = WorkItem::where('uraian_pekerjaan', 'Galian Tanah')->firstOrFail();
        $besi = WorkItem::where('uraian_pekerjaan', 'Pembesian')->firstOrFail();

        $this->assertEqualsWithDelta(10000000, (float) $galian->harga_pekerjaan, 0.01);
        $this->assertEqualsWithDelta(25.0, (float) $galian->bobot, 0.0001);
        $this->assertEqualsWithDelta(75.0, (float) $besi->bobot, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $galian->bobot + (float) $besi->bobot, 0.0001);
    }

    public function test_pekerjaan_tidak_dapat_dihapus_bila_sudah_memiliki_laporan(): void
    {
        ['project' => $project, 'qs' => $qs, 'items' => $items] = $this->proyekContoh();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        $this->actingAs($qs)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 10]],
        ])->assertCreated();

        $this->actingAs($admin)->deleteJson("/api/projects/{$project->id}/work-items/{$items[0]->id}")
            ->assertStatus(422);
    }
}
