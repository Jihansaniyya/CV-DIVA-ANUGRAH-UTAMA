<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ReportDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        $this->actingAs($qs)->getJson("/api/reports/final?project_id={$project->id}")->assertForbidden();
        $this->actingAs($qs)->postJson('/api/reports/export/final', ['project_id' => $project->id])->assertForbidden();
        $this->actingAs($qs)->getJson('/api/reports/documents')->assertForbidden();
        $this->actingAs($qs)->postJson('/api/reports/export/excel', ['tipe' => 'HARIAN', 'project_id' => $project->id])->assertForbidden();
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

        $this->actingAs($this->kontraktor)->postJson('/api/reports/export/excel', [
            'tipe' => 'MINGGUAN', 'project_id' => $project->id, 'period_id' => $periode->id,
        ])->assertCreated();

        $this->assertSame(2, ReportDocument::where('format', 'EXCEL')->count());
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

    public function test_export_excel_menghasilkan_dokumen(): void
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

    public function test_laporan_akhir_merekap_sampai_laporan_progres_terakhir(): void
    {
        $project = $this->konteks['project'];
        $mingguDua = $project->periods()->where('urutan', 2)->firstOrFail();

        $data = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/final?project_id={$project->id}")
            ->assertOk()
            ->json('data');

        // Laporan progres terakhir berada pada minggu II (bulan I).
        $this->assertSame($mingguDua->id, $data['laporan_terakhir']['period_id']);
        $this->assertSame($mingguDua->tanggal_selesai->toDateString(), $data['laporan_terakhir']['tanggal_laporan']);
        $this->assertSame(1, $data['laporan_terakhir']['bulan_ke']);
        $this->assertSame(2, $data['laporan_terakhir']['jumlah_laporan']);

        $baris = collect($data['kategori'])->flatMap(fn ($kategori) => $kategori['items'])->keyBy('work_item_id');
        $this->assertEqualsWithDelta(100.0, $baris[$this->konteks['items'][0]->id]['realisasi_sd_bulan_ini']['volume'], 0.001);
        $this->assertEqualsWithDelta(100.0, $baris[$this->konteks['items'][1]->id]['realisasi_sd_bulan_ini']['volume'], 0.001);
        $this->assertEqualsWithDelta(50.0, $baris[$this->konteks['items'][1]->id]['keterangan_persen'], 0.001);

        // Rekap progres berhenti di periode laporan terakhir dan identik dengan Kurva S.
        $this->assertCount(2, $data['rekap_mingguan']);
        $this->assertCount(1, $data['rekap_bulanan']);
        $kurva = $this->actingAs($this->kontraktor)->getJson("/api/projects/{$project->id}/curve-s")->assertOk()->json('data.titik');
        $this->assertEquals(array_slice($kurva, 0, 2), array_map(
            fn ($titik) => array_diff_key($titik, array_flip(['minggu_ke_romawi', 'jumlah_laporan', 'pekerjaan', 'kendala', 'catatan'])),
            $data['rekap_mingguan'],
        ));

        // Angka akhir sama dengan laporan mingguan minggu II.
        $mingguan = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/weekly?project_id={$project->id}&period_id={$mingguDua->id}")
            ->json('data.rekap');
        $this->assertEqualsWithDelta($mingguan['realisasi_sd_minggu_ini'], $data['ringkasan']['realisasi_kumulatif'], 0.001);
        $this->assertEqualsWithDelta($mingguan['rencana_kumulatif_sd_minggu_ini'], $data['ringkasan']['rencana_kumulatif'], 0.001);
        $this->assertEqualsWithDelta(-37.5, $data['ringkasan']['deviasi'], 0.001);
        $this->assertEqualsWithDelta(62.5, $data['ringkasan']['realisasi_bulan_terakhir'], 0.001);
        $this->assertEqualsWithDelta(0.0, $data['ringkasan']['realisasi_bulan_lalu'], 0.001);
        $this->assertSame($project->status->value, $data['status_proyek']['kode']);
    }

    public function test_laporan_akhir_kosong_bila_belum_ada_laporan_progres_terkirim(): void
    {
        $proyekBaru = $this->proyekContoh($this->konteks['qs'])['project'];

        $data = $this->actingAs($this->kontraktor)
            ->getJson("/api/reports/final?project_id={$proyekBaru->id}")
            ->assertOk()
            ->json('data');

        $this->assertNull($data['laporan_terakhir']);
        $this->assertSame([], $data['kategori']);

        $this->actingAs($this->kontraktor)
            ->postJson('/api/reports/export/final', ['project_id' => $proyekBaru->id])
            ->assertStatus(422);
    }

    public function test_export_excel_laporan_akhir_menghasilkan_dokumen(): void
    {
        Storage::fake('public');
        $project = $this->konteks['project'];
        $laporan = $project->progressReports()->orderByDesc('tanggal_laporan')->firstOrFail();

        $path = UploadedFile::fake()->image('besi.jpg', 1600, 1200)->store('progress/'.$project->id.'/'.$laporan->id, 'public');
        $laporan->photos()->create([
            'progress_detail_id' => $laporan->details()->first()->id,
            'file_path' => $path,
            'original_name' => 'besi.jpg',
            'file_size' => 1000,
            'caption' => 'Pembesian kolom',
            'diunggah_pada' => now(),
        ]);
        $laporan->photos()->create(['file_path' => 'progress/hilang.jpg', 'original_name' => 'hilang.jpg', 'file_size' => 1]);
        $laporan->issues()->create([
            'work_item_id' => $this->konteks['items'][1]->id,
            'jenis_kendala' => 'Cuaca',
            'deskripsi' => 'Hujan deras',
            'tindak_lanjut' => 'Tambah jam kerja',
            'status' => 'TERBUKA',
        ]);

        $this->actingAs($this->kontraktor)
            ->postJson('/api/reports/export/final', ['project_id' => $project->id])
            ->assertCreated()
            ->assertJsonPath('data.format', 'EXCEL')
            ->assertJsonPath('data.tipe_laporan', 'AKHIR');

        $dokumen = ReportDocument::where('tipe_laporan', 'AKHIR')->firstOrFail();
        Storage::disk('public')->assertExists($dokumen->file_path);
        $this->assertStringEndsWith('.xlsx', $dokumen->file_name);

        $reader = IOFactory::createReader('Xlsx');
        $reader->setIncludeCharts(true);
        $buku = $reader->load(Storage::disk('public')->path($dokumen->file_path));

        $this->assertSame([
            'Informasi Proyek', 'Rekap Mingguan', 'Rekap Bulanan', 'Rencana & Realisasi',
            'Kurva S', 'Dokumentasi', 'Rekapitulasi Akhir',
        ], $buku->getSheetNames());

        $teks = fn (string $nama) => collect($buku->getSheetByName($nama)->toArray(null, false, false))
            ->flatten()->filter(fn ($v) => is_string($v))->implode("\n");

        $this->assertStringContainsString('LAPORAN AKHIR PROYEK', $teks('Informasi Proyek'));
        $this->assertStringContainsString('RINGKASAN PROGRES', $teks('Informasi Proyek'));
        $this->assertStringContainsString('Pembesian: 100,000', $teks('Rekap Mingguan'));
        $this->assertStringContainsString('[CUACA] Pembesian: Hujan deras', $teks('Rekap Mingguan'));
        $this->assertStringContainsString('Tambah jam kerja', $teks('Rekap Bulanan'));
        $this->assertStringContainsString('Galian Tanah', $teks('Rencana & Realisasi'));
        $this->assertStringContainsString('Proyek belum dinyatakan selesai', $teks('Rekapitulasi Akhir'));
        $this->assertStringContainsString('Konsultan Pengawas', $teks('Rekapitulasi Akhir'));

        // Grafik Kurva S memuat dua garis: rencana dan realisasi kumulatif.
        $grafik = $buku->getSheetByName('Kurva S')->getChartCollection();
        $this->assertCount(1, $grafik);
        $this->assertCount(2, $grafik[0]->getPlotArea()->getPlotGroup()[0]->getPlotValues());

        // Foto yang tersimpan disisipkan; foto yang berkasnya hilang diberi keterangan.
        $dokumentasi = $buku->getSheetByName('Dokumentasi');
        $this->assertCount(1, $dokumentasi->getDrawingCollection());
        $this->assertStringContainsString('Berkas foto tidak ditemukan', $teks('Dokumentasi'));
        $this->assertStringContainsString('Keterangan foto: Pembesian kolom', $teks('Dokumentasi'));
    }
}
