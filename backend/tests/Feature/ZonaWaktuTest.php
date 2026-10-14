<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\CurveSService;
use App\Services\ProjectStatusService;
use App\Services\ZonaWaktu;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hari ini" mengikuti zona waktu lokasi proyek (WITA, UTC+8), sedangkan timestamp tersimpan dalam UTC.
 * Jam-jam kritis: 15.59 UTC = 23.59 WITA (hari yang sama) dan 16.00 UTC = 00.00 WITA (hari berikutnya).
 */
class ZonaWaktuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        config(['app.zona_lokal' => 'Asia/Makassar']);
    }

    private function pada(string $waktuUtc): void
    {
        $this->travelTo(CarbonImmutable::parse($waktuUtc, 'UTC'));
    }

    public function test_hari_ini_berganti_pada_tengah_malam_wita_bukan_utc(): void
    {
        $this->pada('2026-10-14 15:59:00');
        $this->assertSame('2026-10-14', ZonaWaktu::hariIni()->toDateString());

        $this->pada('2026-10-14 16:00:00');
        $this->assertSame('2026-10-15', ZonaWaktu::hariIni()->toDateString());
        // Bentuknya sama dengan kolom DATE ber-cast (tengah malam zona aplikasi) sehingga aman dibandingkan.
        $this->assertSame(config('app.timezone'), ZonaWaktu::hariIni()->timezoneName);
    }

    public function test_pergantian_bulan_dan_tahun_mengikuti_tanggal_lokal(): void
    {
        $this->pada('2026-10-31 16:30:00');
        $this->assertSame('2026-11-01', ZonaWaktu::hariIni()->toDateString());

        $this->pada('2026-12-31 16:30:00');
        $this->assertSame('2027-01-01', ZonaWaktu::hariIni()->toDateString());
    }

    public function test_status_proyek_berubah_tepat_saat_tanggal_mulai_dan_selesai_lokal_terlewati(): void
    {
        $status = app(ProjectStatusService::class);
        $project = Project::factory()->make(['tanggal_mulai' => '2026-10-15', 'tanggal_selesai' => '2026-10-31']);

        // 23.59 WITA tanggal 14: belum dimulai. 00.00 WITA tanggal 15 (masih tanggal 14 di UTC): sudah berjalan.
        $this->pada('2026-10-14 15:59:00');
        $this->assertSame(ProjectStatus::BELUM_DIMULAI, $status->tentukan($project, 0, 100));
        $this->pada('2026-10-14 16:00:00');
        $this->assertSame(ProjectStatus::BERJALAN, $status->tentukan($project, 0, 100));

        // Hari terakhir masih berjalan sampai 23.59 WITA; 00.00 WITA keesokan harinya terlambat.
        $this->pada('2026-10-31 15:59:00');
        $this->assertSame(ProjectStatus::BERJALAN, $status->tentukan($project, 50, 100));
        $this->pada('2026-10-31 16:00:00');
        $this->assertSame(ProjectStatus::TERLAMBAT, $status->tentukan($project, 50, 100));
    }

    public function test_rencana_sampai_hari_ini_memakai_periode_yang_dimulai_hari_ini_waktu_lokal(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $project = $konteks['project'];
        $mingguDua = $project->periods()->orderBy('urutan')->get()[1]->tanggal_mulai->toDateString();
        $kurva = app(CurveSService::class);

        // Pukul 23.00 WITA sehari sebelum minggu II: rencana baru minggu I (25%).
        $this->pada(CarbonImmutable::parse($mingguDua)->subDay()->format('Y-m-d').' 15:00:00');
        $this->assertEqualsWithDelta(25.0, $kurva->plannedToDate($project), 0.0001);

        // Pukul 01.00 WITA hari pertama minggu II (masih hari sebelumnya di UTC): minggu II sudah terhitung.
        $this->pada(CarbonImmutable::parse($mingguDua)->subDay()->format('Y-m-d').' 17:00:00');
        $this->assertEqualsWithDelta(100.0, $kurva->plannedToDate($project), 0.0001);
        $this->assertEqualsWithDelta(100.0, $kurva->plannedToDateByProject([$project->id])[$project->id], 0.0001);
    }

    public function test_tanggal_progres_di_batas_jadwal_dan_tengah_malam_lokal(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $project = $konteks['project'];
        $mulai = $project->tanggal_mulai->toDateString();
        $kirim = fn (string $tanggal, int $item = 0) => $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $tanggal,
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $konteks['items'][$item]->id, 'volume_realisasi' => 1]],
        ]);

        // 23.59 WITA sehari sebelum mulai: tanggal mulai masih "besok".
        $this->pada(CarbonImmutable::parse($mulai)->subDay()->format('Y-m-d').' 15:59:00');
        $kirim($mulai)->assertStatus(422)->assertJsonValidationErrors('tanggal_laporan');

        // 00.00 WITA tanggal mulai: sah. Sehari sebelum mulai tetap di luar jadwal.
        $this->pada(CarbonImmutable::parse($mulai)->subDay()->format('Y-m-d').' 16:00:00');
        $kirim($mulai)->assertCreated();
        $kirim(CarbonImmutable::parse($mulai)->subDay()->toDateString(), 1)->assertStatus(422);
    }

    public function test_beranda_qs_memuat_proyek_yang_dimulai_hari_ini_waktu_lokal(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $mulai = $konteks['project']->tanggal_mulai->format('Y-m-d');

        // 01.00 WITA tanggal mulai = 17.00 UTC hari sebelumnya: pekerjaan minggu I sudah perlu dilaporkan.
        $this->pada(CarbonImmutable::parse($mulai)->subDay()->format('Y-m-d').' 17:00:00');
        $pekerjaan = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->json('data.pekerjaan_perlu_laporan');

        $this->assertSame([$konteks['items'][0]->id], array_column($pekerjaan, 'work_item_id'));
    }

    public function test_waktu_pada_nama_berkas_dan_dokumen_memakai_jam_lokal(): void
    {
        $this->pada('2026-10-14 17:30:00');

        $this->assertSame('2026-10-15 01:30', ZonaWaktu::sekarang()->format('Y-m-d H:i'));
    }
}
