<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Enums\RoleCode;
use App\Services\ProjectStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_status_ditentukan_dari_jadwal_dan_realisasi(): void
    {
        $project = $this->proyekContoh()['project'];
        $layanan = app(ProjectStatusService::class);
        $sebelumMulai = CarbonImmutable::parse($project->tanggal_mulai)->subDay();
        $tengah = CarbonImmutable::parse($project->tanggal_mulai)->addDays(3);
        $lewatSelesai = CarbonImmutable::parse($project->tanggal_selesai)->addDay();

        $this->assertSame(ProjectStatus::BELUM_DIMULAI, $layanan->tentukan($project, 0, 100, $sebelumMulai));
        $this->assertSame(ProjectStatus::BERJALAN, $layanan->tentukan($project, 10, 100, $sebelumMulai), 'Sudah ada realisasi berarti berjalan.');
        $this->assertSame(ProjectStatus::BERJALAN, $layanan->tentukan($project, 40, 100, $tengah));
        $this->assertSame(ProjectStatus::TERLAMBAT, $layanan->tentukan($project, 80, 100, $lewatSelesai));
        $this->assertSame(ProjectStatus::SELESAI, $layanan->tentukan($project, 100, 100, $tengah));
        $this->assertSame(ProjectStatus::SELESAI, $layanan->tentukan($project, 100, 100, $lewatSelesai));
    }

    public function test_status_menjadi_selesai_saat_seluruh_volume_dilaporkan(): void
    {
        $konteks = $this->proyekContoh();
        $project = $konteks['project'];

        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'status' => 'DIKIRIM',
            'details' => [
                ['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 100],
                ['work_item_id' => $konteks['items'][1]->id, 'volume_realisasi' => 200],
            ],
        ])->assertCreated();

        $this->assertSame(ProjectStatus::SELESAI, $project->refresh()->status);
    }

    public function test_status_tidak_dapat_diubah_manual(): void
    {
        $project = $this->proyekContoh()['project'];
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", ['status' => 'SELESAI', 'lokasi' => 'Kel. Api-Api'])
            ->assertOk();

        // Proyek berjalan minggu ini tanpa realisasi: tetap BERJALAN walau dikirim SELESAI.
        $this->assertSame(ProjectStatus::BERJALAN, $project->refresh()->status);
        $this->assertSame('Kel. Api-Api', $project->lokasi);
    }

    public function test_perintah_sinkron_status_memperbarui_proyek_yang_melewati_jadwal(): void
    {
        $project = $this->proyekContoh()['project'];
        $project->forceFill(['status' => ProjectStatus::BERJALAN])->saveQuietly();
        $updatedAt = $project->refresh()->updated_at;

        $this->travelTo($project->tanggal_selesai->copy()->addDays(2));
        $this->artisan('proyek:sinkron-status')->assertSuccessful();

        $project->refresh();
        $this->assertSame(ProjectStatus::TERLAMBAT, $project->status);
        $this->assertTrue($updatedAt->equalTo($project->updated_at), 'Sinkron status tidak boleh mengubah urutan "terakhir diperbarui".');
    }
}
