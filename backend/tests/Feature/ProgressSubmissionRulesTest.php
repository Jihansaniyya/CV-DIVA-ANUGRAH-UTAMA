<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ProgressPhoto;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\UploadLimitService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Aturan penyimpanan & pengiriman laporan progres yang dijaga backend. */
class ProgressSubmissionRulesTest extends TestCase
{
    use RefreshDatabase;

    /** @var array{project: Project, qs: User, items: array<int,WorkItem>} */
    private array $konteks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        Storage::fake('public');
        $this->konteks = $this->proyekContoh();
        $this->susunRencana($this->konteks);
    }

    private function tanggalMulai(): string
    {
        return $this->konteks['project']->tanggal_mulai->toDateString();
    }

    /** @param array<string,mixed> $data */
    private function simpan(array $data): TestResponse
    {
        return $this->actingAs($this->konteks['qs'])->postJson('/api/progress', $data + [
            'project_id' => $this->konteks['project']->id,
            'tanggal_laporan' => $this->tanggalMulai(),
        ]);
    }

    private function detail(int $index, float $volume): array
    {
        return [['work_item_id' => $this->konteks['items'][$index]->id, 'volume_realisasi' => $volume]];
    }

    private function sisa(int $index): float
    {
        $item = collect($this->actingAs($this->konteks['qs'])->getJson("/api/projects/{$this->konteks['project']->id}/work-items")->json('data'))
            ->firstWhere('id', $this->konteks['items'][$index]->id);

        return (float) $item['sisa_volume'];
    }

    // ---- Kelengkapan bukti ---------------------------------------------------------------------

    public function test_laporan_lengkap_dapat_langsung_dikirim(): void
    {
        $this->simpan([
            'status' => 'DIKIRIM',
            'lokasi' => 'Bontang',
            'keterangan' => 'Galian area utara.',
            'details' => $this->detail(0, 10),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertCreated()->assertJsonPath('data.status', 'DIKIRIM');
    }

    public function test_laporan_dikirim_tanpa_foto_atau_keterangan_ditolak(): void
    {
        $this->simpan([
            'status' => 'DIKIRIM',
            'lokasi' => 'Bontang',
            'keterangan' => 'Galian.',
            'details' => $this->detail(0, 10),
        ])->assertStatus(422)->assertJsonValidationErrors('photos');

        $this->simpan([
            'status' => 'DIKIRIM',
            'lokasi' => 'Bontang',
            'details' => $this->detail(0, 10),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(422)->assertJsonValidationErrors('keterangan');

        $this->assertSame(0, ProgressReport::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_draf_boleh_belum_lengkap_tetapi_tidak_dapat_dikirim_sebelum_dilengkapi(): void
    {
        $id = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 10)])->assertCreated()->json('data.id');

        // Endpoint submit maupun ubah-lalu-kirim tidak dapat melewati pemeriksaan bukti.
        $this->actingAs($this->konteks['qs'])->patchJson("/api/progress/{$id}/submit")
            ->assertStatus(422)->assertJsonValidationErrors(['keterangan', 'photos']);
        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", ['status' => 'DIKIRIM', 'keterangan' => 'Galian'])
            ->assertStatus(422)->assertJsonValidationErrors('photos');
        $this->assertSame('DRAFT', ProgressReport::findOrFail($id)->status->value);

        // Laporan lama tanpa lokasi juga ditolak saat dikirim.
        ProgressReport::whereKey($id)->update(['lokasi' => null]);
        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", [
            'keterangan' => 'Galian',
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertOk();
        ProgressReport::whereKey($id)->update(['lokasi' => null]);
        $this->actingAs($this->konteks['qs'])->patchJson("/api/progress/{$id}/submit")
            ->assertStatus(422)->assertJsonValidationErrors('lokasi');

        ProgressReport::whereKey($id)->update(['lokasi' => 'Bontang']);
        $this->actingAs($this->konteks['qs'])->patchJson("/api/progress/{$id}/submit")->assertOk()->assertJsonPath('data.status', 'DIKIRIM');
    }

    // ---- Selesai / Belum Selesai (kendala) -------------------------------------------------------

    private function kendala(array $ubah = []): array
    {
        return [array_merge([
            'work_item_id' => $this->konteks['items'][0]->id,
            'jenis_kendala' => 'CUACA',
            'deskripsi' => 'Hujan deras sejak siang.',
            'tindak_lanjut' => 'Lembur besok.',
        ], $ubah)];
    }

    private function laporanLengkap(array $data): array
    {
        return $data + [
            'lokasi' => 'Bontang',
            'keterangan' => 'Galian.',
            'details' => $this->detail(0, 10),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ];
    }

    public function test_laporan_selesai_tanpa_kendala_dan_belum_selesai_dengan_kendala_lengkap_dapat_dikirim(): void
    {
        $this->simpan($this->laporanLengkap(['status' => 'DIKIRIM']))->assertCreated();
        $this->simpan($this->laporanLengkap([
            'status' => 'DIKIRIM',
            'tanggal_laporan' => $this->konteks['project']->tanggal_mulai->toDateString(),
            'details' => $this->detail(1, 10),
            'issues' => $this->kendala(['work_item_id' => $this->konteks['items'][1]->id]),
        ]))->assertCreated();
    }

    public function test_kendala_tidak_lengkap_ditolak_saat_dikirim_lewat_api_langsung(): void
    {
        $kasus = [
            'tanpa pekerjaan terkait' => ['work_item_id' => null],
            'pekerjaan yang tidak dilaporkan' => ['work_item_id' => $this->konteks['items'][1]->id],
            'tanpa jenis' => ['jenis_kendala' => null],
            'tanpa tindak lanjut' => ['tindak_lanjut' => null],
        ];

        foreach ($kasus as $nama => $ubah) {
            $response = $this->simpan($this->laporanLengkap(['status' => 'DIKIRIM', 'issues' => $this->kendala($ubah)]));
            $this->assertSame(422, $response->status(), "Kasus {$nama}: ".$response->getContent());
            $response->assertJsonValidationErrors('issues');
        }

        $this->assertSame(0, ProgressReport::count());
    }

    public function test_draf_dengan_kendala_belum_lengkap_boleh_disimpan_tetapi_tidak_dapat_dikirim(): void
    {
        $id = $this->simpan($this->laporanLengkap([
            'status' => 'DRAFT',
            'issues' => $this->kendala(['jenis_kendala' => null, 'tindak_lanjut' => null]),
        ]))->assertCreated()->json('data.id');

        // Jenis yang belum dipilih tetap kosong, tidak berubah menjadi "Lainnya".
        $this->assertDatabaseHas('progress_issues', ['progress_report_id' => $id, 'jenis_kendala' => null]);

        $this->actingAs($this->konteks['qs'])->patchJson("/api/progress/{$id}/submit")
            ->assertStatus(422)->assertJsonValidationErrors('issues');

        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", ['issues' => $this->kendala()])->assertOk();
        $this->actingAs($this->konteks['qs'])->patchJson("/api/progress/{$id}/submit")->assertOk();
    }

    public function test_kendala_tidak_dapat_merujuk_pekerjaan_proyek_lain(): void
    {
        $proyekLain = $this->proyekContoh();

        $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 1),
            'issues' => [['work_item_id' => $proyekLain['items'][0]->id, 'deskripsi' => 'x']],
        ])->assertStatus(422)->assertJsonValidationErrors('issues');
    }

    // ---- Duplikasi & idempotensi ----------------------------------------------------------------

    public function test_pekerjaan_sama_pada_tanggal_sama_tidak_dapat_dicatat_dua_kali(): void
    {
        $this->kirimProgres($this->konteks['qs'], [
            'project_id' => $this->konteks['project']->id,
            'tanggal_laporan' => $this->tanggalMulai(),
            'details' => $this->detail(0, 10),
        ])->assertCreated();

        // Laporan kedua (draf maupun dikirim) untuk pekerjaan & tanggal yang sama ditolak.
        $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 5)])
            ->assertStatus(422)->assertJsonValidationErrors('details');

        // Pekerjaan lain pada tanggal yang sama, atau pekerjaan sama pada tanggal lain, tetap sah.
        $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(1, 5)])->assertCreated();
        $this->kirimProgres($this->konteks['qs'], [
            'project_id' => $this->konteks['project']->id,
            'tanggal_laporan' => $this->konteks['project']->tanggal_mulai->addDay()->toDateString(),
            'details' => $this->detail(0, 5),
        ])->assertCreated();

        // Duplikat di dalam satu laporan juga ditolak.
        $this->simpan([
            'status' => 'DRAFT',
            'tanggal_laporan' => $this->konteks['project']->tanggal_mulai->addDay()->toDateString(),
            'details' => [...$this->detail(1, 1), ...$this->detail(1, 1)],
        ])->assertStatus(422);

        $this->assertSame(3, ProgressReport::count());
    }

    public function test_permintaan_yang_dikirim_ulang_tidak_membuat_laporan_ganda(): void
    {
        $data = [
            'client_request_id' => (string) Str::uuid(),
            'status' => 'DIKIRIM',
            'lokasi' => 'Bontang',
            'keterangan' => 'Galian.',
            'details' => $this->detail(0, 30),
        ];

        $pertama = $this->simpan($data + ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertCreated()->json('data.id');
        $kedua = $this->simpan($data + ['photos' => [UploadedFile::fake()->image('a.jpg')]])->assertOk()->json('data.id');

        $this->assertSame($pertama, $kedua);
        $this->assertSame(1, ProgressReport::count());
        $this->assertSame(1, ProgressPhoto::count());
        $this->assertCount(1, Storage::disk('public')->allFiles());
        // Realisasi tidak terhitung dua kali.
        $this->assertEqualsWithDelta(70.0, $this->sisa(0), 0.0001);
    }

    // ---- Sisa volume ----------------------------------------------------------------------------

    public function test_draf_mengurangi_sisa_volume_secara_konsisten(): void
    {
        $draf = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 30)])->assertCreated()->json('data.id');

        // Form progres (API pekerjaan) dan Beranda QS menunjukkan sisa yang sama: 100 - 30.
        $this->assertEqualsWithDelta(70.0, $this->sisa(0), 0.0001);
        $baris = collect($this->actingAs($this->konteks['qs'])->getJson('/api/dashboard')->json('data.pekerjaan_perlu_laporan'))
            ->firstWhere('work_item_id', $this->konteks['items'][0]->id);
        $this->assertEqualsWithDelta(70.0, $baris['sisa_volume'], 0.0001);
        // Draf belum menjadi realisasi pada Kurva S.
        $this->assertEqualsWithDelta(0.0, (float) $baris['volume_realisasi'], 0.0001);

        $besok = $this->konteks['project']->tanggal_mulai->addDay()->toDateString();
        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $besok, 'details' => $this->detail(0, 71)])
            ->assertStatus(422)->assertJsonValidationErrors('details');
        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $besok, 'details' => $this->detail(0, 70)])->assertCreated();
        $this->assertEqualsWithDelta(0.0, $this->sisa(0), 0.0001);

        // Draf yang dihapus mengembalikan sisa volume.
        $this->actingAs($this->konteks['qs'])->deleteJson("/api/progress/{$draf}")->assertOk();
        $this->assertEqualsWithDelta(30.0, $this->sisa(0), 0.0001);
    }

    public function test_mengubah_draf_tidak_menghitung_volume_draf_itu_sendiri(): void
    {
        $id = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 60)])->assertCreated()->json('data.id');

        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", ['details' => $this->detail(0, 100)])->assertOk();
        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", ['details' => $this->detail(0, 100.5)])
            ->assertStatus(422)->assertJsonValidationErrors('details');
    }

    // ---- Tanggal laporan ------------------------------------------------------------------------

    public function test_tanggal_progres_tidak_boleh_di_masa_depan(): void
    {
        $this->travelTo(CarbonImmutable::parse($this->tanggalMulai().' 04:00:00'));

        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $this->konteks['project']->tanggal_mulai->addDay()->toDateString(), 'details' => $this->detail(0, 1)])
            ->assertStatus(422)->assertJsonValidationErrors('tanggal_laporan');
        $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 1)])->assertCreated();
    }

    public function test_hari_ini_mengikuti_zona_waktu_lokal_bukan_utc(): void
    {
        // 17.00 UTC = 01.00 WITA keesokan harinya; QS di lapangan sudah berada di tanggal berikutnya.
        $this->travelTo(CarbonImmutable::parse($this->tanggalMulai().' 17:00:00', 'UTC'));

        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $this->konteks['project']->tanggal_mulai->addDay()->toDateString(), 'details' => $this->detail(0, 1)])
            ->assertCreated();
    }

    public function test_tanggal_setelah_jadwal_proyek_berakhir_ditolak(): void
    {
        $this->travelTo($this->konteks['project']->tanggal_selesai->addWeek());

        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $this->konteks['project']->tanggal_selesai->addDay()->toDateString(), 'details' => $this->detail(0, 1)])
            ->assertStatus(422)->assertJsonValidationErrors('tanggal_laporan');
        $this->simpan(['status' => 'DRAFT', 'tanggal_laporan' => $this->konteks['project']->tanggal_selesai->toDateString(), 'details' => $this->detail(0, 1)])
            ->assertCreated();
    }

    public function test_laporan_melewati_pergantian_bulan_dan_tahun_masuk_periode_yang_benar(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $project = $this->konteks['project'];

        // Proyek 29 Des 2025 s/d 25 Jan 2026 (sudah lewat): minggu I = 29 Des - 4 Jan.
        $this->actingAs($admin)->putJson("/api/projects/{$project->id}", [
            'tanggal_mulai' => '2025-12-29',
            'tanggal_selesai' => '2026-01-25',
        ])->assertOk();
        $this->susunRencana(['project' => $project->fresh(), 'items' => $this->konteks['items']]);

        foreach (['2025-12-31' => 1, '2026-01-01' => 1, '2026-01-05' => 2] as $tanggal => $minggu) {
            $id = $this->kirimProgres($this->konteks['qs'], [
                'project_id' => $project->id,
                'tanggal_laporan' => $tanggal,
                'details' => $this->detail(0, 10),
            ])->assertCreated()->json('data.id');

            $laporan = ProgressReport::with('period')->findOrFail($id);
            $this->assertSame($tanggal, $laporan->tanggal_laporan->toDateString());
            $this->assertSame($minggu, $laporan->period->urutan, "Periode untuk {$tanggal}");
        }
    }

    // ---- Pengelolaan draf & foto ----------------------------------------------------------------

    public function test_qs_dapat_mengelola_draf_dan_fotonya_sebelum_dikirim(): void
    {
        $qs = $this->konteks['qs'];
        $id = $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 10),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
            'photo_captions' => ['Foto awal'],
        ])->assertCreated()->json('data.id');

        // Buka ulang, ubah volume, tambah foto.
        $this->actingAs($qs)->getJson("/api/progress/{$id}")->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->actingAs($qs)->postJson("/api/progress/{$id}", [
            'keterangan' => 'Galian dilanjutkan.',
            'details' => $this->detail(0, 25),
            'photos' => [UploadedFile::fake()->image('b.jpg')],
        ])->assertOk()->assertJsonPath('data.detail.0.volume_realisasi', 25);

        $foto = ProgressPhoto::where('progress_report_id', $id)->orderBy('id')->get();
        $this->assertCount(2, $foto);

        // Hapus foto: baris database dan berkasnya ikut hilang.
        $this->actingAs($qs)->deleteJson("/api/progress-photos/{$foto[0]->id}")->assertOk();
        $this->assertDatabaseMissing('progress_photos', ['id' => $foto[0]->id]);
        Storage::disk('public')->assertMissing($foto[0]->file_path);
        Storage::disk('public')->assertExists($foto[1]->file_path);

        $this->actingAs($qs)->patchJson("/api/progress/{$id}/submit")->assertOk();

        // Laporan terkirim tidak dapat diubah, dihapus, atau dikurangi fotonya.
        $this->actingAs($qs)->postJson("/api/progress/{$id}", ['keterangan' => 'ubah'])->assertForbidden();
        $this->actingAs($qs)->deleteJson("/api/progress/{$id}")->assertForbidden();
        $this->actingAs($qs)->deleteJson("/api/progress-photos/{$foto[1]->id}")->assertForbidden();
        $this->actingAs($qs)->patchJson("/api/progress/{$id}/submit")->assertForbidden();
        Storage::disk('public')->assertExists($foto[1]->file_path);
    }

    public function test_qs_lain_tidak_dapat_mengubah_draf_milik_qs_lain(): void
    {
        $id = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 10)])->assertCreated()->json('data.id');
        $qsLain = $this->userDenganPeran(RoleCode::QS);
        $this->konteks['project']->assignments()->create(['user_id' => $qsLain->id, 'peran' => 'QS', 'is_primary' => false]);

        $this->actingAs($qsLain)->postJson("/api/progress/{$id}", ['keterangan' => 'x'])->assertForbidden();
        $this->actingAs($qsLain)->patchJson("/api/progress/{$id}/submit")->assertForbidden();
        $this->actingAs($qsLain)->deleteJson("/api/progress/{$id}")->assertForbidden();
    }

    public function test_penyimpanan_gagal_tidak_meninggalkan_berkas_foto(): void
    {
        $id = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 10)])->assertCreated()->json('data.id');

        // Volume melebihi sisa: seluruh perubahan dibatalkan, termasuk foto yang sempat tersimpan.
        $this->actingAs($this->konteks['qs'])->postJson("/api/progress/{$id}", [
            'details' => $this->detail(0, 500),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertStatus(422);

        $this->assertSame(0, ProgressPhoto::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertEqualsWithDelta(10.0, (float) ProgressReport::findOrFail($id)->details()->value('volume_realisasi'), 0.0001);
    }

    public function test_hapus_draf_menghapus_berkas_fotonya(): void
    {
        $id = $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 10),
            'photos' => [UploadedFile::fake()->image('a.jpg')],
        ])->assertCreated()->json('data.id');

        $this->assertNotEmpty(Storage::disk('public')->allFiles());
        $this->actingAs($this->konteks['qs'])->deleteJson("/api/progress/{$id}")->assertOk();
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    // ---- Batas unggah ---------------------------------------------------------------------------

    private function batasUji(): void
    {
        // Batas tetap agar pengujian tidak bergantung pada php.ini mesin yang menjalankannya.
        $this->app->instance(UploadLimitService::class, new class extends UploadLimitService
        {
            public function fotoMaksKb(): int
            {
                return 1024;
            }

            public function fotoMaksJumlah(): int
            {
                return 3;
            }

            public function totalMaksKb(): int
            {
                return 2000;
            }
        });
    }

    public function test_batas_unggah_mengikuti_konfigurasi_server(): void
    {
        $batas = new UploadLimitService;
        config(['progres.foto_maks_kb' => 999999]);

        $data = $this->actingAs($this->konteks['qs'])->getJson('/api/progress/upload-limits')->assertOk()->json('data');
        $php = $batas->iniKeKb((string) ini_get('upload_max_filesize'));
        $this->assertSame($php > 0 ? $php : 999999, $data['foto_maks_kb']);

        $this->assertSame(2048, $batas->iniKeKb('2M'));
        $this->assertSame(8192, $batas->iniKeKb('8M'));
        $this->assertSame(512, $batas->iniKeKb('512K'));
        $this->assertSame(0, $batas->iniKeKb('0'));
        $this->assertSame('2 MB', UploadLimitService::label(2048));

        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);
        $this->actingAs($kontraktor)->getJson('/api/progress/upload-limits')->assertForbidden();
    }

    public function test_foto_kecil_dan_mendekati_batas_diterima(): void
    {
        $this->batasUji();

        $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 1),
            'photos' => [UploadedFile::fake()->image('kecil.jpg')->size(20), UploadedFile::fake()->image('batas.jpg')->size(1000)],
        ])->assertCreated();
    }

    public function test_foto_melebihi_batas_atau_format_tidak_didukung_ditolak_dengan_pesan_jelas(): void
    {
        $this->batasUji();

        $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 1), 'photos' => [UploadedFile::fake()->image('besar.jpg')->size(1500)]])
            ->assertStatus(422)->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'maksimal 1 MB'));

        $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 1), 'photos' => [UploadedFile::fake()->create('dok.pdf', 10, 'application/pdf')]])
            ->assertStatus(422)->assertJsonPath('errors', fn ($e) => str_contains(json_encode($e), 'JPG'));

        // Tiga foto masing-masing di bawah batas, tetapi totalnya melewati batas satu pengiriman.
        $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 1),
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg")->size(900), [1, 2, 3]),
        ])->assertStatus(422)->assertJsonValidationErrors('photos');

        // Jumlah foto melebihi batas.
        $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 1),
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), [1, 2, 3, 4]),
        ])->assertStatus(422)->assertJsonValidationErrors('photos');

        $this->assertSame(0, ProgressReport::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_beberapa_foto_sekaligus_tersimpan(): void
    {
        $this->simpan([
            'status' => 'DRAFT',
            'details' => $this->detail(0, 1),
            'photos' => array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), [1, 2, 3, 4, 5]),
        ])->assertCreated();

        $this->assertSame(5, ProgressPhoto::count());
    }

    // ---- Akses Kontraktor -----------------------------------------------------------------------

    public function test_kontraktor_memantau_semua_proyek_tanpa_dapat_menulis_progres(): void
    {
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);
        $proyekLain = $this->proyekContoh()['project'];
        $draf = $this->simpan(['status' => 'DRAFT', 'details' => $this->detail(0, 1)])->json('data.id');

        // Sesuai rancangan (ARCHITECTURE.md keputusan 7): Kontraktor adalah pemantau seluruh proyek perusahaan.
        $this->actingAs($kontraktor)->getJson("/api/projects/{$proyekLain->id}")->assertOk();
        $this->assertSame(2, $this->actingAs($kontraktor)->getJson('/api/projects')->json('meta.total'));

        // Tidak dapat melihat draf atau menulis progres melalui endpoint QS.
        $this->actingAs($kontraktor)->getJson("/api/progress/{$draf}")->assertForbidden();
        $this->actingAs($kontraktor)->postJson("/api/progress/{$draf}", ['keterangan' => 'x'])->assertForbidden();
        $this->actingAs($kontraktor)->patchJson("/api/progress/{$draf}/submit")->assertForbidden();
        $this->actingAs($kontraktor)->deleteJson("/api/progress/{$draf}")->assertForbidden();

        // Akun nonaktif ditolak pada seluruh endpoint.
        $kontraktor->update(['is_active' => false]);
        $this->actingAs($kontraktor)->getJson('/api/projects')->assertForbidden();
    }

    public function test_qs_yang_tidak_ditugaskan_tidak_dapat_mengakses_proyek(): void
    {
        $qsLain = $this->userDenganPeran(RoleCode::QS);
        $project = $this->konteks['project'];

        $this->actingAs($qsLain)->getJson("/api/projects/{$project->id}")->assertForbidden();
        $this->actingAs($qsLain)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $this->tanggalMulai(),
            'details' => $this->detail(0, 1),
        ])->assertForbidden();
    }
}
