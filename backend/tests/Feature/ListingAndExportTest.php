<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\ReportDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/** Batas paginasi, pilihan proyek di atas 100, urutan Beranda QS, dan export laporan akhir yang berat. */
class ListingAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_parameter_paginasi_divalidasi_dan_dibatasi(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        foreach (['/api/projects', '/api/progress', '/api/users'] as $url) {
            $this->actingAs($admin)->getJson($url.'?per_page=101')->assertStatus(422)->assertJsonValidationErrors('per_page');
            $this->actingAs($admin)->getJson($url.'?per_page=0')->assertStatus(422);
            $this->actingAs($admin)->getJson($url.'?page=0')->assertStatus(422)->assertJsonValidationErrors('page');
            $this->actingAs($admin)->getJson($url.'?per_page=100&page=2')->assertOk();
        }
    }

    public function test_riwayat_dokumen_lebih_dari_50_dapat_ditelusuri_per_halaman(): void
    {
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);
        $project = Project::factory()->create();

        foreach (range(1, 60) as $i) {
            ReportDocument::create([
                'project_id' => $project->id,
                'user_id' => $kontraktor->id,
                'tipe_laporan' => $i % 2 ? 'MINGGUAN' : 'BULANAN',
                'format' => 'EXCEL',
                'file_path' => "reports/{$project->id}/dok-{$i}.xlsx",
                'file_name' => "dok-{$i}.xlsx",
                'digenerate_pada' => now()->subMinutes(100 - $i),
            ]);
        }

        $pertama = $this->actingAs($kontraktor)->getJson('/api/reports/documents?per_page=50')->assertOk();
        $this->assertSame(60, $pertama->json('meta.total'));
        $this->assertSame('dok-60.xlsx', $pertama->json('data.0.file_name'));

        // Dokumen tertua tetap dapat dibuka di halaman berikutnya; tidak ada yang terhapus.
        $kedua = $this->actingAs($kontraktor)->getJson('/api/reports/documents?per_page=50&page=2')->assertOk();
        $this->assertCount(10, $kedua->json('data'));
        $this->assertSame('dok-1.xlsx', $kedua->json('data.9.file_name'));
        $this->assertSame(60, ReportDocument::count());

        $this->assertSame(30, $this->actingAs($kontraktor)->getJson('/api/reports/documents?tipe=BULANAN')->json('meta.total'));
        $this->actingAs($kontraktor)->getJson('/api/reports/documents?tipe=HARIAN')->assertStatus(422);
        $this->actingAs($kontraktor)->getJson('/api/reports/documents?per_page=500')->assertStatus(422);

        // Hanya Kontraktor yang mengakses laporan; dokumen proyek yang sudah dihapus tidak tampil.
        $this->actingAs($this->userDenganPeran(RoleCode::QS))->getJson('/api/reports/documents')->assertForbidden();
        $project->delete();
        $this->assertSame(0, $this->actingAs($kontraktor)->getJson('/api/reports/documents')->json('meta.total'));
    }

    public function test_pilihan_proyek_memuat_seluruh_proyek_walau_lebih_dari_100(): void
    {
        $qs = $this->userDenganPeran(RoleCode::QS);
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        Project::factory()->count(105)->create();
        $milikQs = Project::factory()->count(3)->create(['qs_user_id' => $qs->id]);

        $this->assertCount(108, $this->actingAs($admin)->getJson('/api/projects/options')->assertOk()->json('data'));

        // QS hanya mendapat proyek yang ditugaskan, tetap dengan urutan terbaru lebih dulu.
        $opsiQs = $this->actingAs($qs)->getJson('/api/projects/options')->json('data');
        $this->assertSame($milikQs->sortByDesc('id')->pluck('id')->values()->all(), array_column($opsiQs, 'id'));

        // Halaman Data Proyek dapat menampilkan satu proyek terpilih di luar 100 data pertama.
        $tertua = Project::orderBy('id')->first();
        $this->assertSame([$tertua->id], array_column($this->actingAs($admin)->getJson("/api/projects?id={$tertua->id}")->json('data'), 'id'));
    }

    public function test_urutan_proyek_beranda_qs_sama_dengan_halaman_proyek(): void
    {
        $qs = $this->userDenganPeran(RoleCode::QS);

        foreach (range(1, 7) as $i) {
            $p = Project::factory()->create(['qs_user_id' => $qs->id, 'created_at' => now()->subDays(10 - $i)]);
            // Perubahan proyek lama tidak mengubah urutan.
            if ($i === 1) {
                $p->touch();
            }
        }

        $daftar = array_column($this->actingAs($qs)->getJson('/api/projects?per_page=10')->json('data'), 'id');
        $beranda = array_column($this->actingAs($qs)->getJson('/api/dashboard')->json('data.proyek_ditugaskan'), 'id');

        $this->assertSame($daftar, $beranda);
    }

    public function test_beranda_qs_tanpa_laporan_dan_setelah_pergantian_qs(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        $data = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertSame(0, $data['kpi']['laporan_draft']);
        $this->assertSame(0, $data['kpi']['laporan_dikirim']);
        $this->assertSame([], $data['laporan_terbaru']);
        $this->assertEqualsWithDelta(0.0, $data['proyek_ditugaskan'][0]['progres_aktual'], 0.0001);

        $this->kirimProgres($konteks['qs'], [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 100]],
        ])->assertCreated();

        $data = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->json('data');
        $this->assertSame(1, $data['kpi']['laporan_dikirim']);
        $this->assertEqualsWithDelta(25.0, $data['proyek_ditugaskan'][0]['progres_aktual'], 0.0001);

        // Setelah dialihkan, proyek dan laporannya tidak lagi tampil pada Beranda QS lama.
        $qsBaru = $this->userDenganPeran(RoleCode::QS);
        $this->actingAs($admin)->putJson("/api/projects/{$konteks['project']->id}", ['qs_user_id' => $qsBaru->id])->assertOk();

        $lama = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->json('data');
        $this->assertSame(0, $lama['kpi']['total_proyek']);
        $this->assertSame(0, $lama['kpi']['laporan_dikirim']);
        $this->assertSame([], $lama['laporan_terbaru']);

        $baru = $this->actingAs($qsBaru)->getJson('/api/dashboard')->json('data');
        $this->assertSame($konteks['project']->id, $baru['proyek_ditugaskan'][0]['id']);
    }

    /**
     * Dijalankan di proses terpisah: puluhan foto beresolusi penuh membuat proses PHPUnit bersama melewati
     * memory_limit 128 MB. Pada aplikasi, setiap export berjalan dalam satu request sehingga memori dilepas.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_export_laporan_akhir_dengan_banyak_foto_hanya_memuat_laporan_terkirim(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $project = $konteks['project'];
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $this->kirimProgres($konteks['qs'], [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 60]],
        ])->assertCreated();
        $laporan = $project->progressReports()->firstOrFail();

        // 60 foto berukuran penuh pada laporan terkirim + 1 berkas yang hilang dari penyimpanan.
        $disk = Storage::disk('public');
        $contoh = UploadedFile::fake()->image('contoh.jpg', 1600, 1200)->store("progress/{$project->id}/{$laporan->id}", 'public');

        for ($i = 1; $i <= 60; $i++) {
            $disk->copy($contoh, "progress/{$project->id}/{$laporan->id}/f{$i}.jpg");
            $laporan->photos()->create([
                'file_path' => "progress/{$project->id}/{$laporan->id}/f{$i}.jpg",
                'original_name' => "f{$i}.jpg",
                'file_size' => 1,
                'caption' => "Foto {$i}",
                'diunggah_pada' => now(),
            ]);
        }
        $laporan->photos()->create(['file_path' => 'progress/hilang.jpg', 'original_name' => 'hilang.jpg', 'file_size' => 1]);

        // Draf berfoto tidak boleh ikut ke dokumen.
        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->addDay()->min(now())->toDateString(),
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 5]],
            'photos' => [UploadedFile::fake()->image('draf.jpg')],
        ])->assertCreated();

        $mulai = microtime(true);
        memory_reset_peak_usage();
        $id = $this->actingAs($kontraktor)->postJson('/api/reports/export/final', ['project_id' => $project->id])
            ->assertCreated()->json('data.id');
        $path = ReportDocument::findOrFail($id)->file_path;
        $detik = microtime(true) - $mulai;
        $memoriMb = memory_get_peak_usage(true) / 1048576;
        // Batas longgar untuk mendeteksi regresi besar; terukur ±9 detik dan ±85 MB pada mesin pengembangan.
        $this->assertLessThan(120, $detik);

        $workbook = IOFactory::load(Storage::disk('public')->path($path));
        $this->assertSame(
            ['Informasi Proyek', 'Rekap Mingguan', 'Rekap Bulanan', 'Rencana & Realisasi', 'Kurva S', 'Dokumentasi', 'Rekapitulasi Akhir'],
            $workbook->getSheetNames(),
        );
        $sheet = $workbook->getSheetByName('Dokumentasi');
        // 1 foto bukti dari kirimProgres + 60 foto; berkas hilang tidak disisipkan, draf tidak ikut.
        $this->assertCount(61, $sheet->getDrawingCollection());
        $teks = implode("\n", array_map(fn ($baris) => implode(' ', array_filter($baris)), $sheet->toArray()));
        $this->assertStringContainsString('Berkas foto tidak ditemukan', $teks);
        $this->assertStringNotContainsString('draf.jpg', $teks);
        $this->assertLessThan(256, $memoriMb);

        $workbook->disconnectWorksheets();
    }
}
