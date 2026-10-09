<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Status proyek ditentukan otomatis dari jadwal dan realisasi (bukan dipilih manual):
 *
 *   SELESAI        = realisasi kumulatif >= total bobot pekerjaan (100%)
 *   BELUM_DIMULAI  = hari ini < tanggal_mulai dan belum ada realisasi
 *   TERLAMBAT      = hari ini > tanggal_selesai dan realisasi belum 100%
 *   BERJALAN       = selain kondisi di atas
 *
 * Proyek yang tertinggal dari rencana tetapi masih dalam masa pelaksanaan tetap BERJALAN;
 * ketertinggalan ditunjukkan oleh deviasi Kurva S.
 */
class ProjectStatusService
{
    private const TOLERANSI = 0.005;

    public function __construct(private readonly CurveSService $curve) {}

    public function tentukan(Project $project, float $realisasi, float $totalBobot, ?CarbonImmutable $hariIni = null): ProjectStatus
    {
        $hariIni ??= CarbonImmutable::today();

        if ($totalBobot > 0 && $realisasi >= $totalBobot - self::TOLERANSI) {
            return ProjectStatus::SELESAI;
        }

        if ($hariIni->lessThan($project->tanggal_mulai) && $realisasi <= 0) {
            return ProjectStatus::BELUM_DIMULAI;
        }

        if ($hariIni->greaterThan($project->tanggal_selesai)) {
            return ProjectStatus::TERLAMBAT;
        }

        return ProjectStatus::BERJALAN;
    }

    /** Hitung ulang status satu proyek; mengembalikan status terbaru. */
    public function sinkronkan(Project $project): ProjectStatus
    {
        $status = $this->tentukan(
            $project,
            $this->curve->totalActual($project),
            (float) $project->workItems()->sum('bobot'),
        );

        $this->simpan($project, $status);

        return $status;
    }

    /** Hitung ulang status seluruh proyek dengan query massal. */
    public function sinkronkanSemua(): int
    {
        $projects = Project::query()->get();
        $realisasi = $this->curve->totalActualByProject($projects->pluck('id'));
        $bobot = WorkItem::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->selectRaw('project_id, SUM(bobot) as total')
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $berubah = 0;

        foreach ($projects as $project) {
            $status = $this->tentukan($project, (float) ($realisasi[$project->id] ?? 0), (float) ($bobot[$project->id] ?? 0));
            $berubah += $this->simpan($project, $status) ? 1 : 0;
        }

        return $berubah;
    }

    /**
     * Sinkronisasi karena pergantian hari (tanggal mulai/selesai terlewati) cukup sekali sehari.
     * Dipanggil saat beranda/daftar proyek dibuka sebagai cadangan bila scheduler tidak berjalan.
     */
    public function sinkronkanHarian(): void
    {
        if (Cache::add('status-proyek:'.CarbonImmutable::today()->toDateString(), true, now()->endOfDay())) {
            $this->sinkronkanSemua();
        }
    }

    /** Simpan tanpa menyentuh updated_at agar urutan "terakhir diperbarui" tidak berubah. */
    private function simpan(Project $project, ProjectStatus $status): bool
    {
        if ($project->status === $status) {
            return false;
        }

        // toBase(): query builder Eloquent otomatis mengisi updated_at, query dasar tidak.
        Project::query()->whereKey($project->id)->toBase()->update(['status' => $status->value]);
        $project->status = $status;
        $project->syncOriginalAttribute('status');

        return true;
    }
}
