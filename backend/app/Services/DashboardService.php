<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Collection;

/** Menyusun ringkasan dashboard untuk masing-masing peran. */
class DashboardService
{
    public function __construct(private readonly CurveSService $curve) {}

    public function forUser(User $user): array
    {
        return match (true) {
            $user->isAdmin() => $this->admin(),
            $user->isQs() => $this->qs($user),
            default => $this->kontraktor(),
        };
    }

    public function admin(): array
    {
        $projects = Project::with('qs')->get();

        return [
            'peran' => 'ADMIN',
            'kpi' => [
                'total_proyek' => $projects->count(),
                'total_pekerjaan' => WorkItem::count(),
                'total_pengguna' => User::count(),
                'progres_rata_rata' => $this->averageProgress($projects),
            ],
            'status_proyek' => $this->statusSummary($projects),
            'proyek_terbaru' => $this->projectRows($projects->sortByDesc('created_at')->take(5)),
            'grafik_progres' => $this->projectRows($projects->sortByDesc('updated_at')->take(8))
                ->map(fn ($p) => [
                    'nama_proyek' => $p['nama_proyek'],
                    'rencana' => $p['progres_rencana'],
                    'aktual' => $p['progres_aktual'],
                ])->values()->all(),
            'laporan_terbaru' => $this->latestReports(),
        ];
    }

    public function qs(User $user): array
    {
        $projects = Project::visibleTo($user)->with('qs')->get();
        $projectIds = $projects->pluck('id');

        $laporan = ProgressReport::with(['project', 'period'])
            ->where('user_id', $user->id)
            ->orderByDesc('tanggal_laporan')
            ->limit(5)
            ->get();

        $pekerjaanBelumSelesai = WorkItem::with(['project', 'unit'])
            ->whereIn('project_id', $projectIds)
            ->get()
            ->map(function (WorkItem $item) {
                $realisasi = $item->volumeRealisasi();

                return [
                    'work_item_id' => $item->id,
                    'project_id' => $item->project_id,
                    'nama_proyek' => $item->project?->nama_proyek,
                    'uraian_pekerjaan' => $item->uraian_pekerjaan,
                    'satuan' => $item->unit?->code,
                    'volume' => (float) $item->volume,
                    'volume_realisasi' => $realisasi,
                    'sisa_volume' => round((float) $item->volume - $realisasi, 3),
                    'persentase' => (float) $item->volume > 0 ? round($realisasi / (float) $item->volume * 100, 2) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['sisa_volume'] > 0)
            ->sortBy('persentase')
            ->take(8)
            ->values()
            ->all();

        return [
            'peran' => 'QS',
            'kpi' => [
                'total_proyek' => $projects->count(),
                'total_pekerjaan' => WorkItem::whereIn('project_id', $projectIds)->count(),
                'laporan_draft' => ProgressReport::where('user_id', $user->id)->where('status', ReportStatus::DRAFT)->count(),
                'laporan_dikirim' => ProgressReport::where('user_id', $user->id)->where('status', ReportStatus::DIKIRIM)->count(),
            ],
            'proyek_ditugaskan' => $this->projectRows($projects),
            'pekerjaan_perlu_laporan' => $pekerjaanBelumSelesai,
            'laporan_terbaru' => $laporan->map(fn ($r) => [
                'id' => $r->id,
                'nama_proyek' => $r->project?->nama_proyek,
                'periode' => $r->period?->nama_periode,
                'tanggal_laporan' => $r->tanggal_laporan->toDateString(),
                'status' => $r->status->value,
            ])->all(),
        ];
    }

    public function kontraktor(): array
    {
        $projects = Project::with('qs')->get();
        $rows = $this->projectRows($projects);

        return [
            'peran' => 'KONTRAKTOR',
            'kpi' => [
                'total_proyek' => $projects->count(),
                'proyek_berjalan' => $projects->where('status', ProjectStatus::BERJALAN)->count(),
                'proyek_terlambat' => $rows->where('deviasi', '<', 0)->count(),
                'progres_aktual' => $this->averageProgress($projects),
                'progres_rencana' => round((float) $rows->avg('progres_rencana'), 2),
                'deviasi' => round((float) $rows->avg('deviasi'), 2),
            ],
            'status_proyek' => $this->statusSummary($projects),
            'proyek' => $rows,
            'grafik_progres' => $rows->map(fn ($p) => [
                'nama_proyek' => $p['nama_proyek'],
                'rencana' => $p['progres_rencana'],
                'aktual' => $p['progres_aktual'],
            ])->all(),
            'laporan_terbaru' => $this->latestReports(),
        ];
    }

    /** @param Collection<int,Project> $projects */
    private function projectRows($projects): Collection
    {
        return collect($projects)->map(function (Project $project) {
            $aktual = $this->curve->totalActual($project);
            $rencana = $this->curve->plannedToDate($project);

            return [
                'id' => $project->id,
                'nama_proyek' => $project->nama_proyek,
                'lokasi' => $project->lokasi,
                'nomor_spk' => $project->nomor_spk,
                'tanggal_mulai' => $project->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $project->tanggal_selesai->toDateString(),
                'qs' => $project->qs?->name,
                'status' => $project->status->value,
                'progres_aktual' => $aktual,
                'progres_rencana' => $rencana,
                'deviasi' => round($aktual - $rencana, 2),
            ];
        })->values();
    }

    private function averageProgress($projects): float
    {
        if (count($projects) === 0) {
            return 0.0;
        }

        $total = collect($projects)->sum(fn (Project $p) => $this->curve->totalActual($p));

        return round($total / count($projects), 2);
    }

    private function statusSummary($projects): array
    {
        $koleksi = collect($projects);

        return collect(ProjectStatus::cases())->map(fn (ProjectStatus $status) => [
            'status' => $status->value,
            'label' => $status->label(),
            'jumlah' => $koleksi->where('status', $status)->count(),
        ])->all();
    }

    private function latestReports(): array
    {
        return ProgressReport::with(['project', 'user', 'period'])
            ->where('status', ReportStatus::DIKIRIM)
            ->orderByDesc('tanggal_laporan')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'nama_proyek' => $r->project?->nama_proyek,
                'pelapor' => $r->user?->name,
                'periode' => $r->period?->nama_periode,
                'tanggal_laporan' => $r->tanggal_laporan->toDateString(),
                'bobot_realisasi' => round((float) $r->details()->sum('bobot_realisasi'), 4),
            ])->all();
    }
}
