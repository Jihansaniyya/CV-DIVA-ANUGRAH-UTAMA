<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\WorkPlan;
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
        $this->assertSame(1, $data['kpi']['total_proyek']);
        $this->assertArrayHasKey('progres_rencana', $data['proyek'][0]);
        $this->assertArrayHasKey('progres_aktual', $data['proyek'][0]);
        $this->assertArrayHasKey('deviasi', $data['proyek'][0]);
        $this->assertArrayNotHasKey('status_proyek', $data);
    }

    public function test_dashboard_kontraktor_mengurutkan_proyek_dari_deviasi_paling_tertinggal(): void
    {
        $this->proyekContoh();
        $tertinggal = $this->proyekContoh();

        // Tambah target rencana pada periode yang sudah berjalan tanpa realisasi sehingga deviasinya paling negatif.
        $periode = $tertinggal['project']->periods()->orderBy('urutan')->first();
        $periode->update(['tanggal_mulai' => now()->subWeek()->toDateString()]);
        WorkPlan::create([
            'project_id' => $tertinggal['project']->id,
            'work_item_id' => $tertinggal['items'][0]->id,
            'period_id' => $periode->id,
            'target_volume' => 10,
            'target_persentase' => 10,
            'target_bobot' => 30,
        ]);
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);

        $data = $this->actingAs($kontraktor)->getJson('/api/dashboard')->assertOk()->json('data');

        $deviasi = array_column($data['proyek'], 'deviasi');
        $terurut = $deviasi;
        sort($terurut);

        $this->assertSame($tertinggal['project']->id, $data['proyek'][0]['id']);
        $this->assertSame($terurut, $deviasi);
        $this->assertTrue($data['proyek'][0]['tertinggal']);
        $this->assertArrayNotHasKey('grafik_progres', $data);
    }

    public function test_pekerjaan_perlu_diperbarui_hanya_yang_tertinggal_dari_rencana(): void
    {
        $konteks = $this->proyekContoh();
        $project = $konteks['project'];
        $periods = $project->periods()->orderBy('urutan')->get();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);

        // Galian direncanakan minggu ini (sudah berjalan), pembesian minggu III (belum berjalan).
        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [
                ['work_item_id' => $konteks['items'][0]->id, 'period_id' => $periods[0]->id, 'target_volume' => 100],
                ['work_item_id' => $konteks['items'][1]->id, 'period_id' => $periods[2]->id, 'target_volume' => 200],
            ],
        ])->assertOk();

        // Proyek QS yang sama tetapi belum dimulai: pekerjaannya tidak boleh muncul.
        $belumMulai = $this->proyekContoh($konteks['qs']);
        $belumMulai['project']->update([
            'tanggal_mulai' => now()->addMonth()->toDateString(),
            'tanggal_selesai' => now()->addMonths(2)->toDateString(),
        ]);

        $daftar = $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->assertOk()->json('data.pekerjaan_perlu_laporan');
        $this->assertSame([$konteks['items'][0]->id], array_column($daftar, 'work_item_id'));
        $this->assertEqualsWithDelta(100.0, $daftar[0]['volume_rencana'], 0.001);

        // Setelah realisasi memenuhi rencana, pekerjaan tidak lagi perlu diperbarui.
        $this->actingAs($konteks['qs'])->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'status' => 'DIKIRIM',
            'details' => [['work_item_id' => $konteks['items'][0]->id, 'volume_realisasi' => 100]],
        ])->assertCreated();

        $this->assertSame([], $this->actingAs($konteks['qs'])->getJson('/api/dashboard')->json('data.pekerjaan_perlu_laporan'));
    }
}
