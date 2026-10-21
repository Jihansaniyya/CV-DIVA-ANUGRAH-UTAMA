<?php

namespace Tests\Feature;

use App\Enums\ReportStatus;
use App\Enums\RoleCode;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Pergantian QS mencabut akses QS lama tanpa menghapus laporan historis. */
class QsReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $this->admin = $this->userDenganPeran(RoleCode::ADMIN);
        Storage::fake('public');
    }

    private function gantiQs(Project $project, ?int $qsId): void
    {
        $this->actingAs($this->admin)->putJson("/api/projects/{$project->id}", ['qs_user_id' => $qsId])->assertOk();
    }

    public function test_qs_lama_kehilangan_akses_dan_qs_baru_mendapat_akses_setelah_pergantian(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        ['project' => $project, 'qs' => $qsLama, 'items' => $items] = $konteks;
        $qsBaru = $this->userDenganPeran(RoleCode::QS);

        // Riwayat QS lama: satu laporan terkirim dan satu draf.
        $terkirim = $this->kirimProgres($qsLama, [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 40]],
        ])->assertCreated()->json('data.id');

        $draf = $this->actingAs($qsLama)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $items[1]->id, 'volume_realisasi' => 50]],
        ])->assertCreated()->json('data.id');

        $this->gantiQs($project, $qsBaru->id);

        // QS lama: proyek, laporan, dan aksi tulis ditolak.
        $this->actingAs($qsLama)->getJson("/api/projects/{$project->id}")->assertForbidden();
        $this->assertSame(0, $this->actingAs($qsLama)->getJson('/api/projects')->json('meta.total'));
        $this->assertSame([], $this->actingAs($qsLama)->getJson('/api/projects/options')->json('data'));
        $this->actingAs($qsLama)->getJson("/api/projects/{$project->id}/work-items")->assertForbidden();
        $this->actingAs($qsLama)->getJson("/api/progress/{$terkirim}")->assertForbidden();
        $this->assertSame(0, $this->actingAs($qsLama)->getJson('/api/progress')->json('meta.total'));
        $this->actingAs($qsLama)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 1]],
        ])->assertForbidden();
        $this->actingAs($qsLama)->postJson("/api/progress/{$draf}", ['keterangan' => 'ubah'])->assertForbidden();
        $this->actingAs($qsLama)->patchJson("/api/progress/{$draf}/submit")->assertForbidden();
        $this->actingAs($qsLama)->deleteJson("/api/progress/{$draf}")->assertForbidden();

        // QS baru: dapat membuka proyek dan membuat laporan.
        $this->actingAs($qsBaru)->getJson("/api/projects/{$project->id}")->assertOk();
        $this->kirimProgres($qsBaru, [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->addDay()->toDateString(),
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 10]],
        ])->assertCreated();

        // Laporan historis tetap tersimpan dan tetap dihitung sebagai realisasi.
        $this->assertDatabaseHas('progress_reports', ['id' => $terkirim, 'user_id' => $qsLama->id, 'status' => 'DIKIRIM']);
        $this->assertDatabaseHas('progress_reports', ['id' => $draf, 'user_id' => $qsLama->id, 'status' => 'DRAFT']);
        $this->actingAs($this->admin)->getJson("/api/progress/{$terkirim}")->assertOk();
    }

    public function test_pergantian_berulang_tidak_meninggalkan_akses_qs_sebelumnya(): void
    {
        $konteks = $this->proyekContoh();
        $project = $konteks['project'];
        $qsB = $this->userDenganPeran(RoleCode::QS);
        $qsC = $this->userDenganPeran(RoleCode::QS);

        $this->gantiQs($project, $qsB->id);
        $this->gantiQs($project, $qsC->id);

        foreach ([$konteks['qs'], $qsB] as $qsLama) {
            $this->actingAs($qsLama)->getJson("/api/projects/{$project->id}")->assertForbidden();
        }

        $this->actingAs($qsC)->getJson("/api/projects/{$project->id}")->assertOk();
        $this->assertSame([$qsC->id], ProjectAssignment::where('project_id', $project->id)->pluck('user_id')->all());

        // QS penanggung jawab wajib diisi: mengosongkannya ditolak dan penugasan QS C tetap.
        $this->actingAs($this->admin)->putJson("/api/projects/{$project->id}", ['qs_user_id' => null])
            ->assertStatus(422)->assertJsonValidationErrors(['qs_user_id']);
        $this->actingAs($qsC)->getJson("/api/projects/{$project->id}")->assertOk();
        $this->assertSame([$qsC->id], ProjectAssignment::where('project_id', $project->id)->pluck('user_id')->all());
    }

    public function test_draf_qs_lama_tidak_lagi_mengurangi_sisa_volume(): void
    {
        $konteks = $this->proyekContoh();
        $this->susunRencana($konteks);
        ['project' => $project, 'qs' => $qsLama, 'items' => $items] = $konteks;
        $qsBaru = $this->userDenganPeran(RoleCode::QS);

        $this->actingAs($qsLama)->postJson('/api/progress', [
            'project_id' => $project->id,
            'tanggal_laporan' => $project->tanggal_mulai->toDateString(),
            'status' => 'DRAFT',
            'details' => [['work_item_id' => $items[0]->id, 'volume_realisasi' => 90]],
        ])->assertCreated();

        $this->gantiQs($project, $qsBaru->id);

        $sisa = collect($this->actingAs($qsBaru)->getJson("/api/projects/{$project->id}/work-items")->json('data'))
            ->firstWhere('id', $items[0]->id)['sisa_volume'];

        $this->assertEqualsWithDelta(100.0, $sisa, 0.0001);
        $this->assertSame(1, ProgressReport::where('status', ReportStatus::DRAFT)->count());
    }

    public function test_qs_penanggung_jawab_harus_akun_qs_yang_aktif(): void
    {
        $project = $this->proyekContoh()['project'];
        $kontraktor = $this->userDenganPeran(RoleCode::KONTRAKTOR);
        $admin2 = $this->userDenganPeran(RoleCode::ADMIN);
        $qsNonaktif = $this->userDenganPeran(RoleCode::QS, ['is_active' => false]);

        foreach ([$kontraktor->id, $admin2->id, $qsNonaktif->id, 999999] as $id) {
            $this->actingAs($this->admin)->putJson("/api/projects/{$project->id}", ['qs_user_id' => $id])
                ->assertStatus(422)->assertJsonValidationErrors('qs_user_id');
        }

        $this->actingAs($this->admin)->postJson('/api/projects', [
            'nama_proyek' => 'Proyek Baru',
            'lokasi' => 'Bontang',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->addWeeks(2)->toDateString(),
            'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
            'konsultan_pengawas' => 'CV. Pengawas',
            'nama_site_engineer' => 'Site Engineer',
            'qs_user_id' => $kontraktor->id,
        ])->assertStatus(422)->assertJsonValidationErrors('qs_user_id');
    }

    public function test_admin_tetap_dapat_mengubah_proyek_yang_qs_nya_sudah_dinonaktifkan(): void
    {
        ['project' => $project, 'qs' => $qs] = $this->proyekContoh();
        $qs->update(['is_active' => false]);

        $this->actingAs($this->admin)->putJson("/api/projects/{$project->id}", [
            'qs_user_id' => $qs->id,
        ])->assertOk();
    }

    public function test_migrasi_membersihkan_penugasan_qs_lama_yang_tertinggal(): void
    {
        ['project' => $project, 'qs' => $qs] = $this->proyekContoh();
        $qsLama = $this->userDenganPeran(RoleCode::QS);

        // Kondisi data sebelum perbaikan: penugasan QS lama tidak pernah dicabut.
        DB::table('project_assignments')->insert([
            'project_id' => $project->id, 'user_id' => $qsLama->id, 'peran' => 'QS', 'is_primary' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($qsLama)->getJson("/api/projects/{$project->id}")->assertOk();

        $migrasi = require database_path('migrations/2026_10_14_090000_remove_stale_primary_qs_assignments.php');
        $migrasi->up();

        $this->actingAs($qsLama)->getJson("/api/projects/{$project->id}")->assertForbidden();
        $this->actingAs($qs)->getJson("/api/projects/{$project->id}")->assertOk();
    }
}
