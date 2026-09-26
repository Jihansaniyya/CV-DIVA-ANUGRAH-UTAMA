<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ReportDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private array $konteks;

    private User $kontraktor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();

        $this->konteks = $this->proyekContoh();
        $this->kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);
        $project = $this->konteks['project'];
        $periods = $project->periods()->orderBy('urutan')->get();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        // Rencana: pekerjaan 1 pada minggu I, pekerjaan 2 pada minggu II.
        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [
                ['work_item_id' => $this->konteks['items'][0]->id, 'period_id' => $periods[0]->id, 'target_volume' => 100],
                ['work_item_id' => $this->konteks['items'][1]->id, 'period_id' => $periods[1]->id, 'target_volume' => 200],
            ],
        ])->assertOk();

        // Realisasi minggu I penuh, minggu II separuh.
        $this->actingAs($this->konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $periods[0]->tanggal_selesai->toDateString(),
            'status' => 'DIKIRIM',
            'details' => [['work_item_id' => $this->konteks['items'][0]->id, 'volume_realisasi' => 100]],
        ])->assertCreated();

        $this->actingAs($this->konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $periods[1]->tanggal_selesai->toDateString(),
            'status' => 'DIKIRIM',
            'details' => [['work_item_id' => $this->konteks['items'][1]->id, 'volume_realisasi' => 100]],
        ])->assertCreated();
    }

    public function test_qs_tidak_dapat_mengakses_laporan(): void
    {
        $qs = $this->konteks['qs'];
        $project = $this->konteks['project'];

        $this->actingAs($qs)->getJson("/api/reports/daily?project_id={$project->id}")->assertForbidden();
        $this->actingAs($qs)->getJson("/api/reports/weekly?project_id={$project->id}")->assertForbidden();
        $this->actingAs($qs)->getJson("/api/reports/monthly?project_id={$project->id}")->assertForbidden();
        $this->actingAs($qs)->getJson("/api/reports/milestone?project_id={$project->id}")->assertForbidden();
        $this->actingAs($qs)->getJson('/api/reports/documents')->assertForbidden();
        $this->actingAs($qs)->postJson('/api/reports/export/excel', ['tipe' => 'HARIAN', 'project_id' => $project->id])->assertForbidden();
        $this->actingAs($qs)->postJson('/api/reports/export/word', ['tipe' => 'HARIAN', 'project_id' => $project->id])->assertForbidden();
        $this->actingAs($qs)->getJson("/api/projects/{$project->id}/curve-s")->assertForbidden();
    }

    public function test_laporan_mingguan_mengakumulasi_realisasi_minggu_lalu_dan_minggu_ini(): void
    {
        $project = $this->konteks['project'];
        $mingguDua = $project->periods()->where('urutan', 2)->firstOrFail();

        $data = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/weekly?project_id={$project->id}&period_id={$mingguDua->id}")
            ->assertOk()
            ->json('data');

        $baris = collect($data['kategori'])->flatMap(fn ($kategori) => $kategori['items'])->keyBy('work_item_id');

        $galian = $baris[$this->konteks['items'][0]->id];
        $besi = $baris[$this->konteks['items'][1]->id];

        // Galian selesai pada minggu lalu, besi baru dikerjakan minggu ini.
        $this->assertEqualsWithDelta(100.0, $galian['realisasi_lalu']['volume'], 0.001);
        $this->assertEqualsWithDelta(0.0, $galian['realisasi_ini']['volume'], 0.001);
        $this->assertEqualsWithDelta(100.0, $galian['realisasi_sd']['volume'], 0.001);
        $this->assertEqualsWithDelta(0.0, $besi['realisasi_lalu']['volume'], 0.001);
        $this->assertEqualsWithDelta(100.0, $besi['realisasi_ini']['volume'], 0.001);
        $this->assertEqualsWithDelta(100.0, $besi['realisasi_sd']['volume'], 0.001);

        // Bobot: galian 25% penuh + besi 75% x 50% = 37,5% -> total 62,5%
        $this->assertEqualsWithDelta(62.5, $data['rekap']['realisasi_sd_minggu_ini'], 0.001);
        $this->assertEqualsWithDelta(100.0, $data['rekap']['rencana_kumulatif_sd_minggu_ini'], 0.001);
        $this->assertEqualsWithDelta(-37.5, $data['rekap']['deviasi'], 0.001);
    }

    public function test_laporan_bulanan_menampilkan_jadwal_rencana_dan_rekap_per_periode(): void
    {
        $project = $this->konteks['project'];

        $data = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/monthly?project_id={$project->id}&bulan_ke=1")
            ->assertOk()
            ->json('data');

        $this->assertSame(4, count($data['kolom_periode']));
        $this->assertSame(4, count($data['rekap_periode']));
        $this->assertEqualsWithDelta(25.0, $data['rekap_periode'][0]['rencana_mingguan'], 0.001);
        $this->assertEqualsWithDelta(75.0, $data['rekap_periode'][1]['rencana_mingguan'], 0.001);
        $this->assertEqualsWithDelta(100.0, $data['rekap_periode'][1]['rencana_kumulatif'], 0.001);
        $this->assertEqualsWithDelta(62.5, $data['rekap']['realisasi_sd_bulan_ini'], 0.001);

        $baris = collect($data['kategori'])->flatMap(fn ($kategori) => $kategori['items'])->first();
        $this->assertArrayHasKey('jadwal', $baris);
        $this->assertSame(4, count($baris['jadwal']));
    }

    public function test_laporan_mingguan_menampilkan_rekap_realisasi_per_periode(): void
    {
        $project = $this->konteks['project'];
        $mingguDua = $project->periods()->where('urutan', 2)->firstOrFail();

        $rekap = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/weekly?project_id={$project->id}&period_id={$mingguDua->id}")
            ->assertOk()
            ->json('data.rekap');

        // Minggu lalu: galian 25%; minggu ini: besi 75% x 50% = 37,5%.
        $this->assertEqualsWithDelta(25.0, $rekap['realisasi_minggu_lalu'], 0.001);
        $this->assertEqualsWithDelta(37.5, $rekap['realisasi_minggu_ini'], 0.001);
        $this->assertEqualsWithDelta(62.5, $rekap['realisasi_sd_minggu_ini'], 0.001);
    }

    public function test_laporan_bulanan_menampilkan_realisasi_bulan_lalu_ini_sd_dan_deviasi(): void
    {
        $project = $this->konteks['project'];

        $rekap = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/monthly?project_id={$project->id}&bulan_ke=1")
            ->assertOk()
            ->json('data.rekap');

        $this->assertEqualsWithDelta(0.0, $rekap['realisasi_bulan_lalu'], 0.001);
        $this->assertEqualsWithDelta(62.5, $rekap['realisasi_bulan_ini'], 0.001);
        $this->assertEqualsWithDelta(62.5, $rekap['realisasi_sd_bulan_ini'], 0.001);
        $this->assertEqualsWithDelta(100.0, $rekap['rencana_sd_bulan_ini'], 0.001);
        $this->assertEqualsWithDelta(-37.5, $rekap['deviasi'], 0.001);
    }

    public function test_milestone_tanpa_periode_tetap_tampil_pada_kurva_s(): void
    {
        $project = $this->konteks['project'];
        $mingguDua = $project->periods()->where('urutan', 2)->firstOrFail();

        // Milestone tanpa period_id dipetakan ke periode berdasarkan tanggal target.
        $project->milestones()->create([
            'nama' => 'Pembesian selesai',
            'tanggal_target' => $mingguDua->tanggal_mulai->toDateString(),
            'target_persentase' => 100,
            'status' => 'BELUM_TERCAPAI',
        ]);

        $milestone = $this->actingAs($this->kontraktor)
            ->getJson("/api/projects/{$project->id}/curve-s")
            ->assertOk()
            ->json('data.milestones.0');

        $this->assertSame('Pembesian selesai', $milestone['nama']);
        $this->assertSame($mingguDua->id, $milestone['period_id']);
    }

    public function test_export_mingguan_dan_bulanan_berhasil(): void
    {
        Storage::fake('public');
        $project = $this->konteks['project'];
        $periode = $project->periods()->where('urutan', 2)->firstOrFail();

        $this->actingAs($this->kontraktor)->postJson('/api/reports/export/excel', [
            'tipe' => 'BULANAN', 'project_id' => $project->id, 'bulan_ke' => 1,
        ])->assertCreated();

        $this->actingAs($this->kontraktor)->postJson('/api/reports/export/word', [
            'tipe' => 'MINGGUAN', 'project_id' => $project->id, 'period_id' => $periode->id,
        ])->assertCreated();

        $word = ReportDocument::where('format', 'WORD')->firstOrFail();
        $this->assertFileExists(storage_path('app/public/'.$word->file_path));
        unlink(storage_path('app/public/'.$word->file_path));
    }

    public function test_kontraktor_hanya_melihat_progres_yang_sudah_dikirim(): void
    {
        $project = $this->konteks['project'];

        $draf = $this->actingAs($this->konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $this->konteks['items'][1]->id, 'volume_realisasi' => 1]],
        ])->assertCreated()->json('data.id');

        $daftar = $this->actingAs($this->kontraktor)->getJson('/api/progress?per_page=50')->assertOk()->json('data');

        $this->assertCount(2, $daftar);
        $this->assertSame(['DIKIRIM'], array_values(array_unique(array_column($daftar, 'status'))));
        $this->actingAs($this->kontraktor)->getJson("/api/progress/{$draf}")->assertForbidden();
        $this->actingAs($this->konteks['qs'])->getJson("/api/progress/{$draf}")->assertOk();
    }

    public function test_laporan_harian_menampilkan_seluruh_laporan_pada_rentang_tanggal(): void
    {
        $project = $this->konteks['project'];

        $data = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/daily?project_id={$project->id}&dari={$project->tanggal_mulai->toDateString()}&sampai={$project->tanggal_selesai->toDateString()}")
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['ringkasan']['jumlah_laporan']);
        $this->assertSame(2, $data['ringkasan']['jumlah_dikirim']);
        $this->assertEqualsWithDelta(62.5, $data['ringkasan']['bobot_realisasi'], 0.001);
    }

    public function test_export_excel_dan_word_menghasilkan_dokumen(): void
    {
        Storage::fake('public');
        $project = $this->konteks['project'];
        $periode = $project->periods()->where('urutan', 2)->firstOrFail();

        $this->actingAs($this->kontraktor)->postJson('/api/reports/export/excel', [
            'tipe' => 'MINGGUAN',
            'project_id' => $project->id,
            'period_id' => $periode->id,
        ])->assertCreated()->assertJsonPath('data.format', 'EXCEL');

        $dokumen = ReportDocument::where('format', 'EXCEL')->firstOrFail();
        Storage::disk('public')->assertExists($dokumen->file_path);
        $this->assertStringEndsWith('.xlsx', $dokumen->file_name);
    }

    public function test_export_word_bulanan_tersimpan_sebagai_docx(): void
    {
        $project = $this->konteks['project'];

        $this->actingAs($this->kontraktor)->postJson('/api/reports/export/word', [
            'tipe' => 'BULANAN',
            'project_id' => $project->id,
            'bulan_ke' => 1,
        ])->assertCreated()->assertJsonPath('data.format', 'WORD');

        $dokumen = ReportDocument::where('format', 'WORD')->firstOrFail();
        $this->assertStringEndsWith('.docx', $dokumen->file_name);
        $this->assertFileExists(storage_path('app/public/'.$dokumen->file_path));

        unlink(storage_path('app/public/'.$dokumen->file_path));
    }
}
