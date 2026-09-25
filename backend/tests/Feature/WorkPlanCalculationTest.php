<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Period;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\ProjectScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class WorkPlanCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    private function admin(): User
    {
        return $this->userDenganPeran(RoleCode::ADMIN);
    }

    /** @return Collection<int,Period> dengan key urutan (1 = M-I) */
    private function periode(Project $project): Collection
    {
        return $project->periods()->orderBy('urutan')->get()->keyBy('urutan');
    }

    /**
     * Proyek 6 minggu (M-I s/d M-VI). Beton K-250 (bobot 40,35%) direncanakan M-II s/d M-V
     * sesuai dokumen referensi; pekerjaan lain mewakili sisa harga proyek (total 172.133.983,60).
     *
     * @return array{project: Project, beton: WorkItem, lain: WorkItem}
     */
    private function proyekBeton(): array
    {
        $mulai = now()->startOfWeek();
        $project = Project::factory()->create([
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addWeeks(6)->subDay()->toDateString(),
        ]);
        app(ProjectScheduleService::class)->generateWeeklyPeriods($project);
        $p = $this->periode($project);

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => Unit::where('code', 'm3')->value('id'),
            'uraian_pekerjaan' => 'Pek. Beton K-250 Ready Mix',
            'volume' => 23.87,
            'harga_satuan' => 2909892.81,
            'period_mulai_id' => $p[2]->id,
            'period_selesai_id' => $p[5]->id,
        ])->assertCreated()
            ->assertJsonPath('data.periode_mulai', 'M-II')
            ->assertJsonPath('data.periode_selesai', 'M-V');

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => Unit::where('code', 'ls')->value('id'),
            'uraian_pekerjaan' => 'Pekerjaan lainnya',
            'volume' => 1,
            'harga_satuan' => 102674842.23,
            'period_mulai_id' => $p[1]->id,
            'period_selesai_id' => $p[6]->id,
        ])->assertCreated();

        return [
            'project' => $project,
            'beton' => WorkItem::where('uraian_pekerjaan', 'Pek. Beton K-250 Ready Mix')->firstOrFail(),
            'lain' => WorkItem::where('uraian_pekerjaan', 'Pekerjaan lainnya')->firstOrFail(),
        ];
    }

    private function matriks(Project $project): array
    {
        return $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/work-plans")->assertOk()->json('data');
    }

    private function baris(array $matriks, WorkItem $item): array
    {
        return collect($matriks['baris'])->firstWhere('work_item_id', $item->id);
    }

    public function test_jumlah_periode_mengikuti_durasi_proyek_dengan_label_m(): void
    {
        $mulai = now()->startOfWeek();
        $project = Project::factory()->create([
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addDays(44)->toDateString(), // 45 hari kalender
        ]);
        app(ProjectScheduleService::class)->generateWeeklyPeriods($project);

        $this->assertSame(
            ['M-I', 'M-II', 'M-III', 'M-IV', 'M-V', 'M-VI', 'M-VII'],
            $project->periods()->orderBy('urutan')->pluck('nama_periode')->all(),
        );
        $this->assertSame('M-XXIV', ProjectScheduleService::label(24));
    }

    public function test_harga_satuan_dan_periode_wajib_serta_bobot_manual_diabaikan(): void
    {
        $project = Project::factory()->create();
        app(ProjectScheduleService::class)->generateWeeklyPeriods($project);
        $p = $this->periode($project);
        $unit = Unit::where('code', 'm3')->value('id');

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors(['harga_satuan', 'period_mulai_id', 'period_selesai_id']);

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 10,
            'harga_satuan' => 50000,
            'period_mulai_id' => $p[1]->id,
            'period_selesai_id' => $p[2]->id,
            'bobot' => 12,
            'bobot_manual' => 12,
        ])->assertCreated()->assertJsonPath('data.bobot', 100)->assertJsonMissingPath('data.bobot_manual');
    }

    public function test_periode_selesai_tidak_boleh_sebelum_mulai_atau_milik_proyek_lain(): void
    {
        ['project' => $project] = $this->proyekBeton();
        $p = $this->periode($project);
        $lain = Project::factory()->create();
        app(ProjectScheduleService::class)->generateWeeklyPeriods($lain);

        $data = ['unit_id' => Unit::where('code', 'm3')->value('id'), 'uraian_pekerjaan' => 'Uji', 'volume' => 5, 'harga_satuan' => 1000];

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", $data + [
            'period_mulai_id' => $p[4]->id,
            'period_selesai_id' => $p[2]->id,
        ])->assertStatus(422)->assertJsonValidationErrors('period_selesai_id');

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items", $data + [
            'period_mulai_id' => $lain->periods()->value('id'),
            'period_selesai_id' => $p[2]->id,
        ])->assertStatus(422)->assertJsonValidationErrors('period_mulai_id');
    }

    public function test_kelompok_baru_dan_daftar_pekerjaan_disimpan_sekaligus(): void
    {
        ['project' => $project] = $this->proyekBeton();
        $p = $this->periode($project);
        $m2 = Unit::where('code', 'm2')->value('id');

        $response = $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items/batch", [
            'kategori_baru' => ['kode' => 'C', 'nama' => 'PEKERJAAN FINISHING'],
            'items' => [
                ['unit_id' => $m2, 'uraian_pekerjaan' => 'Plesteran', 'volume' => 10, 'harga_satuan' => 50000, 'period_mulai_id' => $p[5]->id, 'period_selesai_id' => $p[6]->id],
                ['unit_id' => $m2, 'uraian_pekerjaan' => 'Acian', 'volume' => 9, 'harga_satuan' => 40000, 'period_mulai_id' => $p[6]->id, 'period_selesai_id' => $p[6]->id],
            ],
        ])->assertCreated();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.kategori.nama', 'PEKERJAAN FINISHING')
            ->assertJsonPath('data.1.kategori.kode', 'C');

        $plesteran = WorkItem::where('uraian_pekerjaan', 'Plesteran')->firstOrFail();
        $this->assertEquals([5.0, 5.0], $plesteran->workPlans()->orderBy('period_id')->pluck('target_volume')->map(fn ($v) => (float) $v)->all());

        $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/work-items")->assertJsonPath('meta.total_bobot', 100);
    }

    public function test_simpan_kelompok_dan_pekerjaan_gagal_seluruhnya_bila_satu_baris_tidak_valid(): void
    {
        ['project' => $project] = $this->proyekBeton();
        $p = $this->periode($project);
        $m2 = Unit::where('code', 'm2')->value('id');

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-items/batch", [
            'kategori_baru' => ['nama' => 'PEKERJAAN FINISHING'],
            'items' => [
                ['unit_id' => $m2, 'uraian_pekerjaan' => 'Plesteran', 'volume' => 10, 'harga_satuan' => 50000, 'period_mulai_id' => $p[5]->id, 'period_selesai_id' => $p[6]->id],
                ['unit_id' => $m2, 'uraian_pekerjaan' => '', 'volume' => 9, 'harga_satuan' => 40000, 'period_mulai_id' => $p[6]->id, 'period_selesai_id' => $p[2]->id],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.1.uraian_pekerjaan', 'items.1.period_selesai_id']);

        $this->assertSame(0, $project->workCategories()->count());
        $this->assertSame(2, $project->workItems()->count());
    }

    public function test_harga_total_dan_bobot_pekerjaan_dihitung_otomatis(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();

        $this->assertEqualsWithDelta(69459141.37, (float) $beton->harga_pekerjaan, 0.01);
        $this->assertEqualsWithDelta(40.3518, (float) $beton->bobot, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $beton->bobot + (float) $lain->bobot, 0.0001);

        $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/work-items")
            ->assertOk()
            ->assertJsonPath('meta.total_harga_pekerjaan', 172133983.6)
            ->assertJsonPath('meta.total_bobot', 100);
    }

    public function test_pekerjaan_baru_membagi_rata_target_ke_periode_aktif(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();

        $baris = $this->baris($this->matriks($project), $beton);

        $this->assertSame([false, true, true, true, true, false], array_column($baris['periode'], 'aktif'));
        $this->assertEquals([0, 5.9675, 5.9675, 5.9675, 5.9675, 0], array_column($baris['periode'], 'target_volume'));
        $this->assertEqualsWithDelta(0.0, $baris['sisa_volume'], 0.00001);
        $this->assertEqualsWithDelta(40.3518, $baris['total_target_bobot'], 0.0005);
    }

    public function test_bobot_rencana_mingguan_dihitung_dari_target_volume(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();
        $p = $this->periode($project);

        $targets = [1 => 0, 2 => 4, 3 => 8, 4 => 7, 5 => 4.87, 6 => 0];
        $rows = collect($targets)->map(fn ($target, $urutan) => [
            'work_item_id' => $beton->id, 'period_id' => $p[$urutan]->id, 'target_volume' => $target,
        ])->values()->all();

        $matriks = $this->actingAs($this->admin())
            ->postJson("/api/projects/{$project->id}/work-plans", ['rows' => $rows])
            ->assertOk()
            ->json('data');

        $baris = $this->baris($matriks, $beton);
        $bobotPerMinggu = array_column($baris['periode'], 'target_bobot');

        foreach ([0.0, 6.76, 13.52, 11.83, 8.24, 0.0] as $i => $harapan) {
            $this->assertEqualsWithDelta($harapan, $bobotPerMinggu[$i], 0.01, 'M-'.ProjectScheduleService::romawi($i + 1));
        }

        $this->assertEqualsWithDelta(0.0, $baris['sisa_volume'], 0.0001);
        $this->assertEqualsWithDelta(23.87, $baris['total_target_volume'], 0.0001);
        $this->assertEqualsWithDelta(40.35, $baris['total_target_bobot'], 0.01);

        // Seluruh pekerjaan terencana penuh -> rencana kumulatif akhir 100%, dan Kurva S memakai data yang sama.
        $this->assertEqualsWithDelta(100.0, $matriks['total_bobot_rencana'], 0.001);
        $this->assertEqualsWithDelta(100.0, end($matriks['total_per_periode'])['kumulatif'], 0.001);

        $kurva = $this->actingAs($this->admin())->getJson("/api/projects/{$project->id}/curve-s")->assertOk()->json('data');
        $this->assertEqualsWithDelta(100.0, $kurva['ringkasan']['total_bobot_rencana'], 0.001);
        $this->assertSame(['M-I', 'M-II', 'M-III', 'M-IV', 'M-V', 'M-VI'], array_column($kurva['titik'], 'nama_periode'));
        $this->assertEquals(
            array_column($matriks['total_per_periode'], 'kumulatif'),
            array_column($kurva['titik'], 'rencana_kumulatif'),
        );
        $this->assertEqualsWithDelta(
            (float) $lain->fresh()->workPlans()->where('period_id', $p[2]->id)->value('target_bobot') + 6.76,
            $kurva['titik'][1]['rencana'],
            0.01,
        );
    }

    public function test_target_pada_periode_nonaktif_ditolak(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $p = $this->periode($project);

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [['work_item_id' => $beton->id, 'period_id' => $p[1]->id, 'target_volume' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    public function test_target_melebihi_volume_ditolak(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $p = $this->periode($project);

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [['work_item_id' => $beton->id, 'period_id' => $p[2]->id, 'target_volume' => 20]],
        ])->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    public function test_perubahan_rentang_periode_membagi_ulang_target(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $p = $this->periode($project);

        $this->actingAs($this->admin())->putJson("/api/projects/{$project->id}/work-items/{$beton->id}", [
            'period_mulai_id' => $p[3]->id,
            'period_selesai_id' => $p[4]->id,
        ])->assertOk();

        $baris = $this->baris($this->matriks($project), $beton);
        $this->assertEquals([0, 0, 11.935, 11.935, 0, 0], array_column($baris['periode'], 'target_volume'));
    }

    public function test_perubahan_volume_menskalakan_target_proporsional(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $p = $this->periode($project);

        $this->actingAs($this->admin())->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [
                ['work_item_id' => $beton->id, 'period_id' => $p[2]->id, 'target_volume' => 4],
                ['work_item_id' => $beton->id, 'period_id' => $p[3]->id, 'target_volume' => 8],
                ['work_item_id' => $beton->id, 'period_id' => $p[4]->id, 'target_volume' => 7],
                ['work_item_id' => $beton->id, 'period_id' => $p[5]->id, 'target_volume' => 4.87],
            ],
        ])->assertOk();

        // Volume diperkecil setengahnya -> pola target tetap, sisa tetap 0.
        $this->actingAs($this->admin())->putJson("/api/projects/{$project->id}/work-items/{$beton->id}", [
            'volume' => 11.935,
        ])->assertOk();

        $baris = $this->baris($this->matriks($project), $beton);
        $this->assertEquals([0, 2, 4, 3.5, 2.435, 0], array_column($baris['periode'], 'target_volume'));
        $this->assertEqualsWithDelta(0.0, $baris['sisa_volume'], 0.00001);
    }

    public function test_perubahan_harga_menyelaraskan_bobot_rencana(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();

        // Harga pekerjaan lain disamakan dengan beton -> bobot beton menjadi 50%
        $this->actingAs($this->admin())->putJson("/api/projects/{$project->id}/work-items/{$lain->id}", [
            'harga_satuan' => 69459141.37,
        ])->assertOk();

        $this->assertEqualsWithDelta(50.0, (float) $beton->workPlans()->sum('target_bobot'), 0.0005);
    }

    public function test_durasi_proyek_berkurang_menyesuaikan_periode_pekerjaan_dan_rencana(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();

        // 6 minggu -> 4 minggu
        $this->actingAs($this->admin())->putJson("/api/projects/{$project->id}", [
            'tanggal_mulai' => $project->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $project->tanggal_mulai->copy()->addWeeks(4)->subDay()->toDateString(),
        ])->assertOk();

        $p = $this->periode($project);
        $this->assertSame(['M-I', 'M-II', 'M-III', 'M-IV'], $p->pluck('nama_periode')->values()->all());

        $this->assertSame($p[4]->id, $beton->fresh()->period_selesai_id);
        $this->assertSame($p[4]->id, $lain->fresh()->period_selesai_id);

        $matriks = $this->matriks($project);
        $this->assertEquals([0, 7.9567, 7.9567, 7.9566], array_column($this->baris($matriks, $beton)['periode'], 'target_volume'));
        $this->assertEqualsWithDelta(0.0, $this->baris($matriks, $lain)['sisa_volume'], 0.00001);
        $this->assertEqualsWithDelta(100.0, $matriks['total_bobot_rencana'], 0.001);

        // Durasi bertambah lagi -> periode baru ikut terbentuk.
        $this->actingAs($this->admin())->putJson("/api/projects/{$project->id}", [
            'tanggal_mulai' => $project->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $project->tanggal_mulai->copy()->addDays(44)->toDateString(),
        ])->assertOk();

        $this->assertSame(7, $project->periods()->count());
    }
}
