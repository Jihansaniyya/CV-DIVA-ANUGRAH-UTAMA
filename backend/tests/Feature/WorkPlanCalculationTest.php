<?php

namespace Tests\Feature;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\Unit;
use App\Models\WorkItem;
use App\Services\ProjectScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkPlanCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    /**
     * Proyek 5 minggu dengan Beton K-250 (bobot 40,35%) sesuai dokumen referensi.
     * Pekerjaan lain mewakili sisa harga proyek (total 172.133.983,60).
     *
     * @return array{project: Project, beton: WorkItem, lain: WorkItem}
     */
    private function proyekBeton(): array
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $mulai = now()->startOfWeek();
        $project = Project::factory()->create([
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addWeeks(5)->subDay()->toDateString(),
        ]);
        app(ProjectScheduleService::class)->generateWeeklyPeriods($project, true);

        $m3 = Unit::where('code', 'm3')->firstOrFail();
        $ls = Unit::where('code', 'ls')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $m3->id,
            'uraian_pekerjaan' => 'Pek. Beton K-250 Ready Mix',
            'volume' => 23.87,
            'harga_satuan' => 2909892.81,
        ])->assertCreated();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $ls->id,
            'uraian_pekerjaan' => 'Pekerjaan lainnya',
            'volume' => 1,
            'harga_satuan' => 102674842.23,
        ])->assertCreated();

        return [
            'project' => $project,
            'beton' => WorkItem::where('uraian_pekerjaan', 'Pek. Beton K-250 Ready Mix')->firstOrFail(),
            'lain' => WorkItem::where('uraian_pekerjaan', 'Pekerjaan lainnya')->firstOrFail(),
        ];
    }

    public function test_harga_satuan_wajib_dan_bobot_manual_diabaikan(): void
    {
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $project = Project::factory()->create();
        $unit = Unit::where('code', 'm3')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit->id,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 10,
        ])->assertStatus(422)->assertJsonValidationErrors('harga_satuan');

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-items", [
            'unit_id' => $unit->id,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 10,
            'harga_satuan' => 50000,
            'bobot' => 12,
            'bobot_manual' => 12,
        ])->assertCreated()->assertJsonPath('data.bobot', 100)->assertJsonMissingPath('data.bobot_manual');
    }

    public function test_harga_total_dan_bobot_pekerjaan_dihitung_otomatis(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();

        $this->assertEqualsWithDelta(69459141.37, (float) $beton->harga_pekerjaan, 0.01);
        $this->assertEqualsWithDelta(40.3518, (float) $beton->bobot, 0.0001);
        $this->assertEqualsWithDelta(100.0, (float) $beton->bobot + (float) $lain->bobot, 0.0001);

        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $this->actingAs($admin)->getJson("/api/projects/{$project->id}/work-items")
            ->assertOk()
            ->assertJsonPath('meta.total_harga_pekerjaan', 172133983.6)
            ->assertJsonPath('meta.total_bobot', 100);
    }

    public function test_bobot_rencana_mingguan_dihitung_dari_target_volume(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $periods = $project->periods()->orderBy('urutan')->get();

        $this->assertCount(5, $periods);

        $targets = [0, 4, 8, 7, 4.87];
        $rows = [];
        foreach ($periods as $i => $period) {
            $rows[] = ['work_item_id' => $beton->id, 'period_id' => $period->id, 'target_volume' => $targets[$i]];
        }

        $matrix = $this->actingAs($admin)
            ->postJson("/api/projects/{$project->id}/work-plans", ['rows' => $rows])
            ->assertOk()
            ->json('data');

        $baris = collect($matrix['baris'])->firstWhere('work_item_id', $beton->id);
        $bobotPerMinggu = array_column($baris['periode'], 'target_bobot');

        foreach ([0.0, 6.76, 13.52, 11.83, 8.24] as $i => $harapan) {
            $this->assertEqualsWithDelta($harapan, $bobotPerMinggu[$i], 0.01, 'Minggu ke-'.($i + 1));
        }

        $this->assertEqualsWithDelta(0.0, $baris['sisa_volume'], 0.0001);
        $this->assertEqualsWithDelta(23.87, $baris['total_target_volume'], 0.0001);
        $this->assertEqualsWithDelta(40.35, $baris['total_target_bobot'], 0.01);
        $this->assertEqualsWithDelta(40.35, $matrix['total_bobot_rencana'], 0.01);
        $this->assertEqualsWithDelta(40.35, end($matrix['total_per_periode'])['kumulatif'], 0.01);

        // Kurva S memakai rencana yang sama
        $kurva = $this->actingAs($admin)->getJson("/api/projects/{$project->id}/curve-s")->assertOk()->json('data');
        $this->assertEqualsWithDelta(40.35, $kurva['ringkasan']['total_bobot_rencana'], 0.01);
    }

    public function test_perubahan_harga_menyelaraskan_bobot_rencana(): void
    {
        ['project' => $project, 'beton' => $beton, 'lain' => $lain] = $this->proyekBeton();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $period = $project->periods()->orderBy('urutan')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [['work_item_id' => $beton->id, 'period_id' => $period->id, 'target_volume' => 23.87]],
        ])->assertOk();

        // Harga pekerjaan lain disamakan dengan beton -> bobot beton menjadi 50%
        $this->actingAs($admin)->putJson("/api/projects/{$project->id}/work-items/{$lain->id}", [
            'harga_satuan' => 69459141.37,
        ])->assertOk();

        $this->assertEqualsWithDelta(50.0, (float) $beton->workPlans()->firstOrFail()->target_bobot, 0.0001);
    }

    public function test_volume_pekerjaan_tidak_boleh_kurang_dari_total_target_rencana(): void
    {
        ['project' => $project, 'beton' => $beton] = $this->proyekBeton();
        $admin = $this->userDenganPeran(RoleCode::ADMIN);
        $period = $project->periods()->orderBy('urutan')->firstOrFail();

        $this->actingAs($admin)->postJson("/api/projects/{$project->id}/work-plans", [
            'rows' => [['work_item_id' => $beton->id, 'period_id' => $period->id, 'target_volume' => 20]],
        ])->assertOk();

        $this->actingAs($admin)->putJson("/api/projects/{$project->id}/work-items/{$beton->id}", [
            'volume' => 15,
        ])->assertStatus(422)->assertJsonValidationErrors('volume');
    }
}
