<?php

namespace Tests;

use App\Enums\RoleCode;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\ProgressService;
use App\Services\ProjectScheduleService;
use App\Services\WeightCalculatorService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

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

        app(ProjectScheduleService::class)->generateWeeklyPeriods($project);
        app(WeightCalculatorService::class)->recalculateProject($project);

        $project->assignments()->create(['user_id' => $qs->id, 'peran' => 'QS', 'is_primary' => true]);

        return [
            'project' => $project->refresh(),
            'qs' => $qs,
            'items' => [$galian->refresh(), $besi->refresh()],
        ];
    }

    /**
     * Kirim laporan progres yang lengkap (lokasi, keterangan, dan satu foto bukti per pekerjaan) seperti dari form QS.
     * Laporan bertanggal setelah hari ini dikirim dengan waktu dimajukan ke tanggal tersebut, seolah QS
     * mengirimnya pada hari pelaksanaan, karena progres tidak boleh bertanggal di masa depan.
     *
     * @param  array<string,mixed>  $data
     */
    protected function kirimProgres(User $qs, array $data): TestResponse
    {
        if (! $this->diskPalsu) {
            Storage::fake('public');
            $this->diskPalsu = true;
        }

        $data += [
            'status' => 'DIKIRIM',
            'lokasi' => 'Kota Bontang',
            'keterangan' => 'Pekerjaan dilaksanakan sesuai jadwal.',
        ];

        if (! isset($data['photos'])) {
            $pekerjaan = array_column($data['details'] ?? [], 'work_item_id');
            $data['photos'] = array_map(fn ($i) => UploadedFile::fake()->image("bukti-{$i}.jpg"), array_keys($pekerjaan)) ?: [UploadedFile::fake()->image('bukti.jpg')];
            $data['photo_work_item_ids'] = $pekerjaan;
        }

        $tanggal = CarbonImmutable::parse($data['tanggal_laporan'])->toDateString();
        $majukan = $tanggal > ProgressService::hariIni()->toDateString();

        if ($majukan) {
            $this->travelTo(CarbonImmutable::parse($tanggal.' 04:00:00'));
        }

        $response = $this->actingAs($qs)->postJson('/api/progress', $data);

        if ($majukan) {
            $this->travelBack();
        }

        return $response;
    }

    private bool $diskPalsu = false;

    /**
     * Rencana 100%: seluruh volume pekerjaan pertama pada periode 1, pekerjaan kedua pada periode 2.
     * Progres baru hanya dapat ditambahkan setelah bobot rencana proyek mencapai 100%.
     */
    protected function susunRencana(array $konteks): void
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
}
