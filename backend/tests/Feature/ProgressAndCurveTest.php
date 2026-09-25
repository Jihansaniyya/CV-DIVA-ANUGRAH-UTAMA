<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\ProgressReport;
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

    public function test_qs_dapat_menyimpan_laporan_progres_beserta_foto_material_dan_kendala(): void
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
            'materials' => [['nama_material' => 'Semen PCC', 'jumlah' => 20, 'satuan' => 'sak']],
            'issues' => [[
                'jenis_kendala' => 'CUACA',
                'deskripsi' => 'Hujan deras sore hari.',
                'alasan_keterlambatan' => 'Curah hujan tinggi.',
                'tindak_lanjut' => 'Menambah jam kerja.',
            ]],
            'photos' => [UploadedFile::fake()->image('progres.jpg')],
        ]);

        $response->assertCreated();

        $laporan = ProgressReport::with(['details', 'materials', 'issues', 'photos'])->firstOrFail();

        // Bobot galian 25%, realisasi 50/100 -> kontribusi 12,5%
        $this->assertEqualsWithDelta(50.0, (float) $laporan->details->first()->persentase_realisasi, 0.0001);
        $this->assertEqualsWithDelta(12.5, (float) $laporan->details->first()->bobot_realisasi, 0.0001);
        $this->assertSame(1, $laporan->materials->count());
        $this->assertSame(1, $laporan->issues->count());
        $this->assertSame(1, $laporan->photos->count());
        $this->assertNotNull($laporan->period_id);
        Storage::disk('public')->assertExists($laporan->photos->first()->file_path);
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
}
