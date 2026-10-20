<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\ProgressDetail;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkPlan;
use Illuminate\Support\Collection;

/** Menyusun ringkasan dashboard untuk masing-masing peran. */
class DashboardService
{
    public function __construct(
        private readonly CurveSService $curve,
        private readonly ProgressService $progress,
    ) {}

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
        // Urutan sama dengan halaman Data Proyek (terbaru dibuat lebih dulu); Beranda mengambil 5 teratas.
        $projects = Project::visibleTo($user)->with('qs')->terbaru()->get();
        $projectIds = $projects->pluck('id');

        // Hanya laporan pada proyek yang masih ditugaskan; laporan proyek yang sudah dialihkan ke QS lain
        // tidak lagi dapat dibuka sehingga tidak ikut dihitung.
        $laporanSaya = fn () => ProgressReport::where('user_id', $user->id)->whereIn('project_id', $projectIds);

        $laporan = $laporanSaya()->with(['project', 'period'])
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $pekerjaanBelumSelesai = $this->pekerjaanTertinggal($projects);

        return [
            'peran' => 'QS',
            'kpi' => [
                'total_proyek' => $projects->count(),
                'total_pekerjaan' => WorkItem::whereIn('project_id', $projectIds)->count(),
                'laporan_draft' => $laporanSaya()->where('status', ReportStatus::DRAFT)->count(),
                'laporan_dikirim' => $laporanSaya()->where('status', ReportStatus::DIKIRIM)->count(),
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

        // Urutkan dari deviasi paling tertinggal (paling negatif); nilai sama diurutkan menurut nama proyek.
        $rows = $this->projectRows($projects)
            ->map(function (array $row) {
                // Tertinggal: proyek yang belum selesai dengan realisasi di bawah rencana s/d hari ini.
                $row['tertinggal'] = $row['status'] !== ProjectStatus::SELESAI->value && $row['deviasi'] < 0;

                return $row;
            })
            ->sortBy([['deviasi', 'asc'], ['nama_proyek', 'asc']])
            ->values();

        return [
            'peran' => 'KONTRAKTOR',
            // Jumlah per kategori dihitung di halaman dari daftar proyek agar selalu sama dengan isi tabel.
            'kpi' => ['total_proyek' => $projects->count()],
            'proyek' => $rows,
            'laporan_terbaru' => $this->latestReports(),
        ];
    }

    /** @param Collection<int,Project> $projects */
    private function projectRows($projects): Collection
    {
        $projects = collect($projects);
        $ids = $projects->pluck('id');

        // Hitung progres semua proyek sekaligus (dua query) agar tidak ada query berulang per proyek.
        $aktualPerProyek = $this->curve->totalActualByProject($ids);
        $rencanaPerProyek = $this->curve->plannedToDateByProject($ids);

        return $projects->map(function (Project $project) use ($aktualPerProyek, $rencanaPerProyek) {
            $aktual = round((float) ($aktualPerProyek[$project->id] ?? 0), 4);
            $rencana = round((float) ($rencanaPerProyek[$project->id] ?? 0), 4);

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
            ->whereHas('project') // lewati laporan milik proyek yang sudah dihapus
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

    /**
     * Pekerjaan yang realisasinya tertinggal dari rencana sampai hari ini, pada proyek yang sudah
     * dimulai dan belum selesai. Hanya pekerjaan ini yang memang dapat dan perlu dilaporkan QS.
     *
     *   rencana s/d hari ini = SUM(work_plans) pada periode dengan tanggal_mulai <= hari ini
     *   realisasi            = SUM(progress_details) laporan DIKIRIM
     *   kekurangan           = rencana - realisasi (diurutkan dari bobot kekurangan terbesar)
     *
     * Seluruh angka diambil dengan query agregat agar tidak ada query per pekerjaan.
     *
     * @param  Collection<int,Project>  $projects
     */
    private function pekerjaanTertinggal(Collection $projects, int $batas = 8): array
    {
        $hariIni = ZonaWaktu::hariIni()->toDateString();
        $projectIds = $projects
            ->filter(fn (Project $p) => $p->tanggal_mulai->toDateString() <= $hariIni && $p->status !== ProjectStatus::SELESAI)
            ->pluck('id');

        if ($projectIds->isEmpty()) {
            return [];
        }

        $rencana = WorkPlan::query()
            ->join('periods', 'periods.id', '=', 'work_plans.period_id')
            ->whereIn('work_plans.project_id', $projectIds)
            ->whereDate('periods.tanggal_mulai', '<=', $hariIni)
            ->selectRaw('work_plans.work_item_id as work_item_id, SUM(work_plans.target_volume) as volume, SUM(work_plans.target_bobot) as bobot')
            ->groupBy('work_plans.work_item_id')
            ->get()
            ->keyBy('work_item_id');

        $realisasi = ProgressDetail::query()
            ->join('progress_reports', 'progress_reports.id', '=', 'progress_details.progress_report_id')
            ->whereIn('progress_reports.project_id', $projectIds)
            ->where('progress_reports.status', ReportStatus::DIKIRIM->value)
            ->selectRaw('progress_details.work_item_id as work_item_id, SUM(progress_details.volume_realisasi) as volume, SUM(progress_details.bobot_realisasi) as bobot')
            ->groupBy('progress_details.work_item_id')
            ->get()
            ->keyBy('work_item_id');

        // Sisa volume memakai aturan yang sama dengan validasi backend (terkirim + draf QS aktif).
        $terpakai = $this->progress->volumeTerpakai($rencana->keys());

        return WorkItem::with(['project', 'unit'])
            ->whereIn('project_id', $projectIds)
            ->whereIn('id', $rencana->keys())
            ->get()
            ->map(function (WorkItem $item) use ($rencana, $realisasi, $terpakai) {
                $volume = (float) $item->volume;
                $volRealisasi = round((float) ($realisasi[$item->id]->volume ?? 0), 3);
                $volRencana = round((float) ($rencana[$item->id]->volume ?? 0), 3);

                return [
                    'work_item_id' => $item->id,
                    'project_id' => $item->project_id,
                    'nama_proyek' => $item->project?->nama_proyek,
                    'uraian_pekerjaan' => $item->uraian_pekerjaan,
                    'satuan' => Unit::tampilan($item->unit?->code),
                    'volume' => $volume,
                    'volume_realisasi' => $volRealisasi,
                    'volume_rencana' => $volRencana,
                    'sisa_volume' => max(round($volume - (float) ($terpakai[$item->id] ?? 0), 3), 0.0),
                    'persentase' => $volume > 0 ? round($volRealisasi / $volume * 100, 2) : 0.0,
                    'kekurangan_bobot' => round((float) ($rencana[$item->id]->bobot ?? 0) - (float) ($realisasi[$item->id]->bobot ?? 0), 4),
                ];
            })
            ->filter(fn (array $row) => $row['sisa_volume'] > 0 && $row['volume_rencana'] - $row['volume_realisasi'] > 0.0005)
            ->sortByDesc('kekurangan_bobot')
            ->take($batas)
            ->values()
            ->all();
    }
}
