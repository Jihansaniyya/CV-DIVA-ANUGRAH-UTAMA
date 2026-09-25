<?php

namespace Tests;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\ProjectScheduleService;
use App\Services\WeightCalculatorService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Master data (peran & satuan) selalu tersedia pada setiap pengujian. */
    protected function seedMasterData(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(UnitSeeder::class);
    }

    protected function userDenganPeran(RoleCode $role, array $atribut = []): User
    {
        return User::factory()->role($role)->create($atribut);
    }

    /**
     * Bangun proyek lengkap: 2 pekerjaan berharga, periode mingguan, dan QS penanggung jawab.
     *
     * @return array{project: Project, qs: User, items: array<int,WorkItem>}
     */
    protected function proyekContoh(?User $qs = null): array
    {
        $qs ??= $this->userDenganPeran(RoleCode::QS);

        $project = Project::factory()->create(['qs_user_id' => $qs->id]);

        $m3 = Unit::where('code', 'm3')->firstOrFail();
        $kg = Unit::where('code', 'kg')->firstOrFail();

        $galian = WorkItem::factory()->create([
            'project_id' => $project->id,
            'unit_id' => $m3->id,
            'uraian_pekerjaan' => 'Galian Tanah',
            'volume' => 100,
            'harga_satuan' => 100000,
            'urutan' => 1,
        ]);

        $besi = WorkItem::factory()->create([
            'project_id' => $project->id,
            'unit_id' => $kg->id,
            'uraian_pekerjaan' => 'Pembesian',
            'volume' => 200,
            'harga_satuan' => 150000,
            'urutan' => 2,
        ]);

        app(ProjectScheduleService::class)->generateWeeklyPeriods($project, true);
        app(WeightCalculatorService::class)->recalculateProject($project);

        $project->assignments()->create(['user_id' => $qs->id, 'peran' => 'QS', 'is_primary' => true]);

        return [
            'project' => $project->refresh(),
            'qs' => $qs,
            'items' => [$galian->refresh(), $besi->refresh()],
        ];
    }
}
