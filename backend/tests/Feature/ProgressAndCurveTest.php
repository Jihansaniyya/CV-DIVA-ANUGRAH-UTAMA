<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ProgressReport;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgressAndCurveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    /** Rencana: seluruh volume pekerjaan pertama pada periode 1, pekerjaan kedua pada periode 2. */
    private function susunRencana(array $konteks): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $project = $konteks['project'];
        $periods = $project->periods()->orderBy('urutan')->get();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [
                ['work_item_id' => $konteks['items'][0]->id, 'period_id' => $periods[0]->id, 'target_volume' => 100],
                ['work_item_id' => $konteks['items'][1]->id, 'period_id' => $periods[1]->id, 'target_volume' => 200],
            ],
        ])->assertOk();
    }

    public function test_qs_dapat_menyimpan_laporan_progres_beserta_foto_dan_kendala(): void
    {
        Storage::fake('public');
        $konteks = $this->proyekContoh();

        $response = $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->toDateString(),
            'keterangan' => 'Pekerjaan galian dimulai.',
            'lokasi' => 'Bontang',
            'status' => 'DIKIRIM',
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 50]],
            'issues' => [[
                'jenis_kendala' => 'CUACA',
                'deskripsi' => 'Hujan deras sore hari.',
                'alasan_keterlambatan' => 'Curah hujan tinggi.',
                'tindak_lanjut' => 'Menambah jam kerja.',
            ]],
            'photos' => [UploadedFile::fake()->image('progres.jpg')],
        ]);

        $response->assertCreated();

        $laporan = ProgressReport::with(['details', 'issues', 'photos'])->firstOrFail();

        // Bobot galian 25%, realisasi 50/100 -> kontribusi 12,5%
        $this->assertEqualsWithDelta(50.0, (float) $laporan->details->first()->persentase_realisasi, 0.0001);
        $this->assertEqualsWithDelta(12.5, (float) $laporan->details->first()->bobot_realisasi, 0.0001);
        $this->assertSame(1, $laporan->issues->count());
        // Alasan keterlambatan yang masih dikirim terpisah digabung ke deskripsi kendala.
        $this->assertSame('Hujan deras sore hari. Curah hujan tinggi.', $laporan->issues->first()->deskripsi);
        $this->assertNull($laporan->issues->first()->alasan_keterlambatan);
        $this->assertSame(1, $laporan->photos->count());
        $this->assertNotNull($laporan->period_id);
        Storage::disk('public')->assertExists($laporan->photos->first()->file_path);
    }

    public function test_tanggal_progres_di_luar_jadwal_proyek_ditolak(): void
    {
        $konteks = $this->proyekContoh();

        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->subDay()->toDateString(),
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('tanggal_laporan');

        $this->assertSame(0, ProgressReport::count());
    }

    public function test_volume_realisasi_tidak_boleh_melebihi_volume_rencana(): void
    {
        $konteks = $this->proyekContoh();

        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 150]],
        ])->assertStatus(422)->assertJsonValidationErrors('details');
    }

    public function test_total_target_rencana_tidak_boleh_melebihi_volume_pekerjaan(): void
    {
        $konteks = $this->proyekContoh();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $periods = $konteks['project']->periods()->orderBy('urutan')->get();

        $this->actingAs($admin)->postJson("/api/projects/{$konteks['project']->id}/work-plans", [
            'rows' => [
                ['work_item_id' => $konteks['items'][0]->id, 'period_id' => $periods[0]->id, 'target_volume' => 80],
                ['work_item_id' => $konteks['items'][0]->id, 'period_id' => $periods[1]->id, 'target_volume' => 40],
            ],
        ])->assertStatus(422);
    }

    public function test_kurva_s_menghitung_rencana_realisasi_dan_deviasi(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);

        $project = $konteks['project'];
        $periods = $project->periods()->orderBy('urutan')->get();

        // Realisasi periode 1 hanya separuh dari rencana -> deviasi negatif.
        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $periods[0]->tanggal_selesai->toDateString(),
            'status' => 'DIKIRIM',
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 50]],
        ])->assertCreated();

        $response = $this->actingAs($this->userDenganPeran(RoleCode::KONTRAKTOR))->getJson("/api/projects/{$project->id}/curve-s")->assertOk();

        $titik = $response->json('data.titik');

        $this->assertEqualsWithDelta(25.0, $titik[0]['rencana_kumulatif'], 0.0001);
        $this->assertEqualsWithDelta(12.5, $titik[0]['aktual_kumulatif'], 0.0001);
        $this->assertEqualsWithDelta(-12.5, $titik[0]['deviasi'], 0.0001);
        $this->assertEqualsWithDelta(100.0, $titik[1]['rencana_kumulatif'], 0.0001);
        $this->assertEqualsWithDelta(12.5, $response->json('data.ringkasan.progres_aktual'), 0.0001);
    }

    public function test_laporan_draft_tidak_dihitung_pada_progres_aktual(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->toDateString(),
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 100]],
        ])->assertCreated();

        $sebelum = $this->actingAs($kontraktor)->getJson("/api/projects/{$konteks['project']->id}/curve-s")
            ->json('data.ringkasan.progres_aktual');

        $this->assertEqualsWithDelta(0.0, $sebelum, 0.0001);

        $laporan = ProgressReport::firstOrFail();
        $this->actingAs($konteks['qs'])->patchJson("/api/progress/{$laporan->id}/submit")->assertOk();

        $sesudah = $this->actingAs($kontraktor)->getJson("/api/projects/{$konteks['project']->id}/curve-s")
            ->json('data.ringkasan.progres_aktual');

        $this->assertEqualsWithDelta(25.0, $sesudah, 0.0001);
    }

    public function test_foto_progres_diperkecil_menjadi_jpeg_saat_diunggah(): void
    {
        Storage::fake('public');
        $konteks = $this->proyekContoh();

        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $konteks['project']->id,
            'tanggal_laporan' => $konteks['project']->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 10]],
            'photos' => [UploadedFile::fake()->image('lapangan.png', 3200, 2400)],
        ])->assertCreated();

        $foto = ProgressReport::with('photos')->firstOrFail()->photos->first();
        $path = Storage::disk('public')->path($foto->file_path);
        [$lebar, $tinggi, $tipe] = getimagesize($path);

        $this->assertStringEndsWith('.jpg', $foto->file_path);
        $this->assertSame(IMAGETYPE_JPEG, $tipe);
        $this->assertSame([1600, 1200], [$lebar, $tinggi]);
        $this->assertSame(filesize($path), (int) $foto->file_size);
        $this->assertCount(1, Storage::disk('public')->allFiles('progress'), 'Berkas PNG asli dihapus setelah dikonversi.');
    }

    public function test_foto_diputar_sesuai_orientasi_exif_kamera(): void
    {
        // JPEG 400x200 dengan EXIF Orientation = 6 (kamera diputar 90° searah jarum jam).
        ob_start();
        imagejpeg(imagecreatetruecolor(400, 200));
        $jpeg = (string) ob_get_clean();
        $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('n', 0x0112).pack('n', 3).pack('N', 1).pack('n', 6).pack('n', 0).pack('N', 0);
        $app1 = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\0\0".$tiff;

        $sumber = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
        $tujuan = tempnam(sys_get_temp_dir(), 'hasil').'.jpg';
        file_put_contents($sumber, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        $this->assertTrue(app(ImageService::class)->perkecil($sumber, $tujuan));
        [$lebar, $tinggi] = getimagesize($tujuan);
        $this->assertSame([200, 400], [$lebar, $tinggi]);

        @unlink($sumber);
        @unlink($tujuan);
    }
}
