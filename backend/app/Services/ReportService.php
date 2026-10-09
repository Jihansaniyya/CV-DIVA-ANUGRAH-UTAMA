<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\Period;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Menyusun data laporan harian, mingguan, bulanan, dan milestone.
 *
 * Struktur laporan mingguan & bulanan mengikuti format resmi yang dipakai
 * CV Diva Anugrah Utama (lihat laporan-mingguan.png dan laporan-bulanan.png).
 *
 * Akumulasi realisasi:
 *   realisasi minggu lalu   = SUM(volume) laporan DIKIRIM s/d hari sebelum periode berjalan
 *   realisasi minggu ini    = SUM(volume) laporan DIKIRIM dalam rentang periode berjalan
 *   realisasi s/d minggu ini = minggu lalu + minggu ini
 *   bobot realisasi         = volume realisasi / volume rencana x bobot pekerjaan
 */
class ReportService
{
    public function __construct(private readonly CurveSService $curve) {}

    /** Informasi header yang dipakai seluruh jenis laporan. */
    public function header(Project $project): array
    {
        return [
            'nama_proyek' => $project->nama_proyek,
            'pekerjaan' => $project->nama_proyek,
            'lokasi' => $project->lokasi,
            'sumber_dana' => $project->sumber_dana,
            'tahun_anggaran' => $project->tahun_anggaran,
            'nomor_spk' => $project->nomor_spk,
            'tanggal_spk' => $project->tanggal_spk?->toDateString(),
            'tanggal_mulai' => $project->tanggal_mulai->toDateString(),
            'tanggal_selesai' => $project->tanggal_selesai->toDateString(),
            'jangka_waktu_hari' => $project->jangka_waktu_hari,
            'kontraktor_pelaksana' => $project->kontraktor_pelaksana,
            'konsultan_pengawas' => $project->konsultan_pengawas,
            'nama_site_engineer' => $project->nama_site_engineer,
            'nama_pelaksana_lapangan' => $project->nama_pelaksana_lapangan,
        ];
    }

    /** Laporan harian: seluruh laporan progres QS dalam rentang tanggal. */
    public function daily(Project $project, string $dari, string $sampai): array
    {
        $reports = $project->progressReports()
            ->with(['user', 'period', 'details.workItem.unit', 'photos', 'issues.workItem'])
            ->whereBetween('tanggal_laporan', [$dari, $sampai])
            ->orderBy('tanggal_laporan')
            ->get();

        return [
            'header' => $this->header($project),
            'periode' => ['dari' => $dari, 'sampai' => $sampai],
            'laporan' => $reports->map(fn ($report) => [
                'id' => $report->id,
                'tanggal_laporan' => $report->tanggal_laporan->toDateString(),
                'periode' => $report->period?->nama_periode,
                'pelapor' => $report->user?->name,
                'status' => $report->status->value,
                'lokasi' => $report->lokasi,
                'cuaca' => $report->cuaca,
                'keterangan' => $report->keterangan,
                'dikirim_pada' => $report->dikirim_pada?->toIso8601String(),
                'detail' => $report->details->map(fn ($d) => [
                    'uraian_pekerjaan' => $d->workItem?->uraian_pekerjaan,
                    'satuan' => $d->workItem?->unit?->code,
                    'volume_rencana' => (float) ($d->workItem?->volume ?? 0),
                    'volume_realisasi' => (float) $d->volume_realisasi,
                    'persentase_realisasi' => (float) $d->persentase_realisasi,
                    'bobot_realisasi' => (float) $d->bobot_realisasi,
                    'keterangan' => $d->keterangan,
                ])->all(),
                'kendala' => $report->issues->map(fn ($i) => [
                    'jenis_kendala' => $i->jenis_kendala,
                    'pekerjaan' => $i->workItem?->uraian_pekerjaan,
                    'deskripsi' => ProgressService::gabungKendala($i->deskripsi, $i->alasan_keterlambatan),
                    'tindak_lanjut' => $i->tindak_lanjut,
                    'status' => $i->status,
                ])->all(),
                'foto' => $report->photos->map(fn ($f) => [
                    'url' => $f->url(),
                    'caption' => $f->caption,
                    'diunggah_pada' => $f->diunggah_pada?->toIso8601String(),
                ])->all(),
            ])->all(),
            'ringkasan' => [
                'jumlah_laporan' => $reports->count(),
                'jumlah_dikirim' => $reports->where('status', ReportStatus::DIKIRIM)->count(),
                'bobot_realisasi' => round((float) $reports->where('status', ReportStatus::DIKIRIM)
                    ->flatMap->details->sum('bobot_realisasi'), 4),
            ],
        ];
    }

    /** Laporan mingguan sesuai format laporan-mingguan.png. */
    public function weekly(Project $project, Period $period): array
    {
        $awalProyek = $project->tanggal_mulai->toDateString();
        $mulai = CarbonImmutable::parse($period->tanggal_mulai);
        $selesai = CarbonImmutable::parse($period->tanggal_selesai);

        $lalu = $mulai->subDay()->lessThan(CarbonImmutable::parse($awalProyek))
            ? collect()
            : $this->curve->actualVolumeBetween($project, $awalProyek, $mulai->subDay()->toDateString());

        $ini = $this->curve->actualVolumeBetween($project, $mulai->toDateString(), $selesai->toDateString());

        $kategori = $this->groupedRows($project, function (WorkItem $item) use ($lalu, $ini) {
            $volLalu = (float) ($lalu[$item->id]->volume ?? 0);
            $bobotLalu = (float) ($lalu[$item->id]->bobot ?? 0);
            $volIni = (float) ($ini[$item->id]->volume ?? 0);
            $bobotIni = (float) ($ini[$item->id]->bobot ?? 0);
            $volSd = round($volLalu + $volIni, 3);
            $bobotSd = round($bobotLalu + $bobotIni, 4);

            return [
                'realisasi_lalu' => ['volume' => $volLalu, 'bobot' => $bobotLalu],
                'realisasi_ini' => ['volume' => $volIni, 'bobot' => $bobotIni],
                'realisasi_sd' => ['volume' => $volSd, 'bobot' => $bobotSd],
                'keterangan_persen' => (float) $item->volume > 0
                    ? round($volSd / (float) $item->volume * 100, 2)
                    : 0.0,
            ];
        });

        $rencanaKumulatif = round((float) $project->workPlans()
            ->whereHas('period', fn ($q) => $q->where('urutan', '<=', $period->urutan))
            ->sum('target_bobot'), 4);

        $total = $this->totalOf($kategori, ['realisasi_lalu', 'realisasi_ini', 'realisasi_sd']);
        $realisasiSd = $total['realisasi_sd']['bobot'];

        return [
            'header' => $this->header($project),
            'periode' => [
                'period_id' => $period->id,
                'bulan_ke' => $period->bulan_ke,
                'bulan_ke_romawi' => ProjectScheduleService::romawi($period->bulan_ke),
                'minggu_ke' => $period->minggu_ke,
                'minggu_ke_romawi' => ProjectScheduleService::romawi($period->minggu_ke),
                'nama_periode' => $period->nama_periode,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
            ],
            'kategori' => $kategori,
            'total' => $total,
            'rekap' => [
                'realisasi_minggu_lalu' => $total['realisasi_lalu']['bobot'],
                'realisasi_minggu_ini' => $total['realisasi_ini']['bobot'],
                'realisasi_sd_minggu_ini' => $realisasiSd,
                'rencana_kumulatif_sd_minggu_ini' => $rencanaKumulatif,
                'deviasi' => round($realisasiSd - $rencanaKumulatif, 4),
            ],
        ];
    }

    /** Laporan bulanan sesuai format laporan-bulanan.png (grid jangka waktu + rekap rencana/realisasi/deviasi). */
    public function monthly(Project $project, int $bulanKe): array
    {
        $periods = $project->periods()->orderBy('urutan')->get();
        $periodeBulanIni = $periods->where('bulan_ke', $bulanKe);

        if ($periodeBulanIni->isEmpty()) {
            $periodeBulanIni = $periods->take(ProjectScheduleService::MINGGU_PER_BULAN);
        }

        $awalProyek = $project->tanggal_mulai->toDateString();
        $mulaiBulan = CarbonImmutable::parse($periodeBulanIni->first()->tanggal_mulai);
        $selesaiBulan = CarbonImmutable::parse($periodeBulanIni->last()->tanggal_selesai);

        $bulanLalu = $mulaiBulan->subDay()->lessThan(CarbonImmutable::parse($awalProyek))
            ? collect()
            : $this->curve->actualVolumeBetween($project, $awalProyek, $mulaiBulan->subDay()->toDateString());
        $bulanIni = $this->curve->actualVolumeBetween($project, $mulaiBulan->toDateString(), $selesaiBulan->toDateString());

        $rencanaPerPeriode = $project->workPlans()
            ->selectRaw('period_id, work_item_id, target_bobot, target_volume')
            ->get()
            ->groupBy('work_item_id');

        $kategori = $this->groupedRows($project, function (WorkItem $item) use ($periods, $rencanaPerPeriode, $bulanLalu, $bulanIni) {
            $plans = ($rencanaPerPeriode[$item->id] ?? collect())->keyBy('period_id');

            $volLalu = (float) ($bulanLalu[$item->id]->volume ?? 0);
            $bobotLalu = (float) ($bulanLalu[$item->id]->bobot ?? 0);
            $volIni = (float) ($bulanIni[$item->id]->volume ?? 0);
            $bobotIni = (float) ($bulanIni[$item->id]->bobot ?? 0);

            return [
                'jadwal' => $periods->map(fn (Period $p) => [
                    'period_id' => $p->id,
                    'bobot' => round((float) ($plans[$p->id]->target_bobot ?? 0), 4),
                    'volume' => round((float) ($plans[$p->id]->target_volume ?? 0), 3),
                ])->all(),
                'realisasi_bulan_lalu' => ['volume' => $volLalu, 'bobot' => $bobotLalu],
                'realisasi_bulan_ini' => ['volume' => $volIni, 'bobot' => $bobotIni],
                'realisasi_sd_bulan_ini' => [
                    'volume' => round($volLalu + $volIni, 3),
                    'bobot' => round($bobotLalu + $bobotIni, 4),
                ],
                'keterangan_persen' => (float) $item->volume > 0
                    ? round(($volLalu + $volIni) / (float) $item->volume * 100, 2)
                    : 0.0,
            ];
        });

        $total = $this->totalOf($kategori, ['realisasi_bulan_lalu', 'realisasi_bulan_ini', 'realisasi_sd_bulan_ini']);
        $aktualPerPeriode = $this->curve->actualByPeriod($project);
        $hariIni = CarbonImmutable::now()->startOfDay();

        $rencanaKum = 0.0;
        $aktualKum = 0.0;
        $rekapPeriode = [];

        foreach ($periods as $period) {
            $rencana = round((float) $project->workPlans()->where('period_id', $period->id)->sum('target_bobot'), 4);
            $aktual = round((float) ($aktualPerPeriode[$period->id] ?? 0), 4);
            $sudahBerjalan = CarbonImmutable::parse($period->tanggal_mulai)->lessThanOrEqualTo($hariIni);

            $rencanaKum = round($rencanaKum + $rencana, 4);

            if ($sudahBerjalan) {
                $aktualKum = round($aktualKum + $aktual, 4);
            }

            $rekapPeriode[] = [
                'period_id' => $period->id,
                'urutan' => $period->urutan,
                'nama_periode' => $period->nama_periode,
                'bulan_ke' => $period->bulan_ke,
                'minggu_ke' => $period->minggu_ke,
                'minggu_ke_romawi' => ProjectScheduleService::romawi($period->minggu_ke),
                'tanggal_mulai' => $period->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $period->tanggal_selesai->toDateString(),
                'rencana_mingguan' => $rencana,
                'rencana_kumulatif' => $rencanaKum,
                'realisasi_mingguan' => $sudahBerjalan ? $aktual : null,
                'realisasi_kumulatif' => $sudahBerjalan ? $aktualKum : null,
                'deviasi' => $sudahBerjalan ? round($aktualKum - $rencanaKum, 4) : null,
            ];
        }

        $realisasiSdBulanIni = $total['realisasi_sd_bulan_ini']['bobot'] ?? 0;
        $rencanaSdBulanIni = round((float) $project->workPlans()
            ->whereHas('period', fn ($q) => $q->where('bulan_ke', '<=', $bulanKe))
            ->sum('target_bobot'), 4);

        return [
            'header' => $this->header($project),
            'periode' => [
                'bulan_ke' => $bulanKe,
                'bulan_ke_romawi' => ProjectScheduleService::romawi($bulanKe),
                'tanggal_mulai' => $mulaiBulan->toDateString(),
                'tanggal_selesai' => $selesaiBulan->toDateString(),
                'jumlah_bulan' => (int) ($periods->max('bulan_ke') ?: 1),
            ],
            'kolom_periode' => $periods->map(fn (Period $p) => [
                'period_id' => $p->id,
                'bulan_ke' => $p->bulan_ke,
                'bulan_ke_romawi' => ProjectScheduleService::romawi($p->bulan_ke),
                'minggu_ke' => $p->minggu_ke,
                'minggu_ke_romawi' => ProjectScheduleService::romawi($p->minggu_ke),
                'nama_periode' => $p->nama_periode,
            ])->all(),
            'kategori' => $kategori,
            'total' => $total,
            'rekap_periode' => $rekapPeriode,
            'rekap' => [
                'realisasi_bulan_lalu' => $total['realisasi_bulan_lalu']['bobot'] ?? 0,
                'realisasi_bulan_ini' => $total['realisasi_bulan_ini']['bobot'] ?? 0,
                'realisasi_sd_bulan_ini' => $realisasiSdBulanIni,
                'rencana_sd_bulan_ini' => $rencanaSdBulanIni,
                'deviasi' => round($realisasiSdBulanIni - $rencanaSdBulanIni, 4),
            ],
        ];
    }

    /**
     * Laporan akhir: rekap pelaksanaan proyek sampai laporan progres terakhir yang sudah dikirim.
     *
     * Tidak ada data tersimpan baru. Seluruh angka diturunkan dari sumber yang sama dengan laporan
     * mingguan/bulanan dan Kurva S, sehingga perubahan progres langsung tercermin di sini:
     *   - realisasi per pekerjaan = logika laporan bulanan untuk bulan laporan terakhir
     *     (s/d bulan lalu, bulan terakhir, s/d akhir);
     *   - rencana per pekerjaan  = SUM(work_plans) sampai periode laporan terakhir;
     *   - rekap progres per minggu/bulan = titik Kurva S sampai periode laporan terakhir,
     *     rekap bulanan hanya menjumlahkan minggu di dalam bulannya (tidak dihitung ganda);
     *   - uraian pekerjaan, kendala, dan dokumentasi = isi laporan progres DIKIRIM.
     */
    public function final(Project $project): array
    {
        $laporanTerakhir = $project->progressReports()->submitted()
            ->orderByDesc('tanggal_laporan')
            ->orderByDesc('id')
            ->first();

        $periods = $project->periods()->orderBy('urutan')->get();
        $periodeTerakhir = $laporanTerakhir === null ? null : ($periods->firstWhere('id', $laporanTerakhir->period_id)
            ?? $periods->last(fn (Period $p) => $p->tanggal_mulai->lessThanOrEqualTo($laporanTerakhir->tanggal_laporan)));

        $kurva = $this->curve->build($project);

        $dasar = [
            'header' => $this->header($project),
            'status_proyek' => ['kode' => $project->status->value, 'label' => $project->status->label()],
            'keterangan_proyek' => $project->keterangan,
            'kurva_s' => [
                'titik' => $kurva['titik'],
                'total_bobot_rencana' => $kurva['ringkasan']['total_bobot_rencana'],
            ],
        ];

        if ($laporanTerakhir === null || $periodeTerakhir === null) {
            return $dasar + [
                'laporan_terakhir' => null,
                'kategori' => [],
                'total' => null,
                'rekap_bulanan' => [],
                'rekap_mingguan' => [],
                'dokumentasi' => [],
                'kendala' => [],
                'ringkasan' => null,
                'kesimpulan' => [],
            ];
        }

        $bulanTerakhir = $periodeTerakhir->bulan_ke;
        $periodeBulanTerakhir = $periods->where('bulan_ke', $bulanTerakhir);
        $awalProyek = $project->tanggal_mulai->toDateString();
        $mulaiBulan = CarbonImmutable::parse($periodeBulanTerakhir->first()->tanggal_mulai);
        $selesaiBulan = CarbonImmutable::parse($periodeBulanTerakhir->last()->tanggal_selesai);

        $bulanLalu = $mulaiBulan->subDay()->lessThan(CarbonImmutable::parse($awalProyek))
            ? collect()
            : $this->curve->actualVolumeBetween($project, $awalProyek, $mulaiBulan->subDay()->toDateString());
        $bulanIni = $this->curve->actualVolumeBetween($project, $mulaiBulan->toDateString(), $selesaiBulan->toDateString());

        $rencanaSd = $project->workPlans()
            ->whereHas('period', fn ($q) => $q->where('urutan', '<=', $periodeTerakhir->urutan))
            ->selectRaw('work_item_id, SUM(target_volume) as volume, SUM(target_bobot) as bobot')
            ->groupBy('work_item_id')
            ->get()
            ->keyBy('work_item_id');

        $kategori = $this->groupedRows($project, function (WorkItem $item) use ($bulanLalu, $bulanIni, $rencanaSd) {
            $volume = (float) $item->volume;
            $volLalu = (float) ($bulanLalu[$item->id]->volume ?? 0);
            $bobotLalu = (float) ($bulanLalu[$item->id]->bobot ?? 0);
            $volIni = (float) ($bulanIni[$item->id]->volume ?? 0);
            $bobotIni = (float) ($bulanIni[$item->id]->bobot ?? 0);
            $volSd = round($volLalu + $volIni, 3);
            $bobotSd = round($bobotLalu + $bobotIni, 4);
            $volRencana = round((float) ($rencanaSd[$item->id]->volume ?? 0), 3);
            $bobotRencana = round((float) ($rencanaSd[$item->id]->bobot ?? 0), 4);
            $persen = $volume > 0 ? round($volSd / $volume * 100, 2) : 0.0;

            return [
                'rencana_sd' => ['volume' => $volRencana, 'bobot' => $bobotRencana],
                'persen_rencana' => $volume > 0 ? round($volRencana / $volume * 100, 2) : 0.0,
                'realisasi_bulan_lalu' => ['volume' => $volLalu, 'bobot' => $bobotLalu],
                'realisasi_bulan_ini' => ['volume' => $volIni, 'bobot' => $bobotIni],
                'realisasi_sd_bulan_ini' => ['volume' => $volSd, 'bobot' => $bobotSd],
                'keterangan_persen' => $persen,
                'sisa_volume' => round(max($volume - $volSd, 0), 3),
                'selisih_volume' => round($volSd - $volRencana, 3),
                'selisih_bobot' => round($bobotSd - $bobotRencana, 4),
                'selesai' => $volume > 0 && $volSd >= $volume - 0.0005,
            ];
        });

        $total = $this->totalOf($kategori, ['rencana_sd', 'realisasi_bulan_lalu', 'realisasi_bulan_ini', 'realisasi_sd_bulan_ini']);

        // Isi laporan progres terkirim sampai laporan terakhir, dikelompokkan per periode (minggu).
        $laporan = $project->progressReports()->submitted()
            ->with(['details.workItem.unit', 'issues.workItem', 'photos.detail.workItem', 'period'])
            ->whereDate('tanggal_laporan', '<=', $laporanTerakhir->tanggal_laporan)
            ->orderBy('tanggal_laporan')
            ->orderBy('id')
            ->get();
        $laporanPerPeriode = $laporan->groupBy('period_id');

        // Titik Kurva S sampai periode laporan terakhir menjadi rekap progres mingguan.
        $rekapMingguan = collect($kurva['titik'])
            ->filter(fn (array $titik) => $titik['urutan'] <= $periodeTerakhir->urutan)
            ->map(function (array $titik) use ($laporanPerPeriode) {
                $isi = $laporanPerPeriode->get($titik['period_id'], collect());

                return $titik + [
                    'minggu_ke_romawi' => ProjectScheduleService::romawi($titik['minggu_ke']),
                    'jumlah_laporan' => $isi->count(),
                    'pekerjaan' => $this->uraianDilaksanakan($isi),
                    'kendala' => $this->daftarKendala($isi),
                    'catatan' => $isi->pluck('keterangan')->map(fn ($k) => trim((string) $k))->filter()->unique()->values()->all(),
                ];
            })
            ->values();

        $rekapBulanan = $rekapMingguan->groupBy('bulan_ke')->map(function (Collection $minggu, int $bulanKe) use ($laporanPerPeriode) {
            $akhir = $minggu->last();
            $sudahBerjalan = $minggu->contains(fn (array $titik) => $titik['aktual'] !== null);
            $isi = $minggu->flatMap(fn (array $titik) => $laporanPerPeriode->get($titik['period_id'], collect()));

            return [
                'bulan_ke' => $bulanKe,
                'bulan_ke_romawi' => ProjectScheduleService::romawi($bulanKe),
                'tanggal_mulai' => $minggu->first()['tanggal_mulai'],
                'tanggal_selesai' => $akhir['tanggal_selesai'],
                'jumlah_minggu' => $minggu->count(),
                'jumlah_laporan' => $isi->count(),
                'rencana' => round((float) $minggu->sum('rencana'), 4),
                'rencana_kumulatif' => $akhir['rencana_kumulatif'],
                'realisasi' => $sudahBerjalan ? round((float) $minggu->sum(fn (array $titik) => $titik['aktual'] ?? 0), 4) : null,
                'realisasi_kumulatif' => $minggu->whereNotNull('aktual_kumulatif')->last()['aktual_kumulatif'] ?? null,
                'deviasi' => $minggu->whereNotNull('deviasi')->last()['deviasi'] ?? null,
                'pekerjaan' => $this->uraianDilaksanakan($isi),
                'kendala' => $this->daftarKendala($isi),
            ];
        })->values();

        $titikTerakhir = $rekapMingguan->last();
        $rencanaKumulatif = (float) $titikTerakhir['rencana_kumulatif'];
        $realisasiKumulatif = (float) ($titikTerakhir['aktual_kumulatif'] ?? $total['realisasi_sd_bulan_ini']['bobot']);
        $totalRencana = (float) $kurva['ringkasan']['total_bobot_rencana'];
        $semuaItem = collect($kategori)->flatMap(fn (array $k) => $k['items']);
        $kendala = $laporan->flatMap(fn ($r) => $this->daftarKendala(collect([$r])));

        $ringkasan = [
            'realisasi_periode_terakhir' => (float) ($titikTerakhir['aktual'] ?? 0),
            'realisasi_bulan_lalu' => $total['realisasi_bulan_lalu']['bobot'],
            'realisasi_bulan_terakhir' => $total['realisasi_bulan_ini']['bobot'],
            'realisasi_kumulatif' => round($realisasiKumulatif, 4),
            'rencana_kumulatif' => round($rencanaKumulatif, 4),
            'deviasi' => round($realisasiKumulatif - $rencanaKumulatif, 4),
            'total_rencana' => round($totalRencana, 4),
            'sisa_progres' => round(max($totalRencana - $realisasiKumulatif, 0), 4),
            'jumlah_pekerjaan' => $semuaItem->count(),
            'jumlah_pekerjaan_selesai' => $semuaItem->where('selesai', true)->count(),
            'jumlah_kendala' => $kendala->count(),
            'jumlah_kendala_terbuka' => $kendala->where('status', '!=', 'SELESAI')->count(),
            'jumlah_foto' => $laporan->sum(fn ($r) => $r->photos->count()),
        ];

        return $dasar + [
            'laporan_terakhir' => [
                'tanggal_laporan' => $laporanTerakhir->tanggal_laporan->toDateString(),
                'period_id' => $periodeTerakhir->id,
                'nama_periode' => $periodeTerakhir->nama_periode,
                'minggu_ke' => $periodeTerakhir->minggu_ke,
                'minggu_ke_romawi' => ProjectScheduleService::romawi($periodeTerakhir->minggu_ke),
                'bulan_ke' => $bulanTerakhir,
                'bulan_ke_romawi' => ProjectScheduleService::romawi($bulanTerakhir),
                'tanggal_mulai' => $periodeTerakhir->tanggal_mulai->toDateString(),
                'tanggal_selesai' => $periodeTerakhir->tanggal_selesai->toDateString(),
                'bulan_tanggal_mulai' => $mulaiBulan->toDateString(),
                'bulan_tanggal_selesai' => $selesaiBulan->toDateString(),
                'jumlah_laporan' => $laporan->count(),
            ],
            'kategori' => $kategori,
            'total' => $total,
            'rekap_bulanan' => $rekapBulanan->all(),
            'rekap_mingguan' => $rekapMingguan->all(),
            'dokumentasi' => $laporan->flatMap(fn ($r) => $r->photos->map(fn ($foto) => [
                'id' => $foto->id,
                'file_path' => $foto->file_path,
                'url' => $foto->url(),
                'caption' => $foto->caption,
                'diunggah_pada' => $foto->diunggah_pada?->toIso8601String(),
                'tanggal_laporan' => $r->tanggal_laporan->toDateString(),
                'nama_periode' => $r->period?->nama_periode,
                'lokasi' => $r->lokasi,
                'uraian_pekerjaan' => $foto->detail?->workItem?->uraian_pekerjaan,
                'keterangan' => $r->keterangan,
            ]))->values()->all(),
            'kendala' => $kendala->values()->all(),
            'ringkasan' => $ringkasan,
            'kesimpulan' => $this->kesimpulanAkhir($project, $periodeTerakhir, $ringkasan),
        ];
    }

    /**
     * Pekerjaan yang dilaporkan pada sekumpulan laporan progres, volume dijumlahkan per pekerjaan.
     *
     * @param  Collection<int,ProgressReport>  $laporan
     * @return list<array{uraian:string,satuan:?string,volume:float,bobot:float}>
     */
    private function uraianDilaksanakan(Collection $laporan): array
    {
        return $laporan->flatMap->details
            ->filter(fn ($d) => (float) $d->volume_realisasi > 0 && $d->workItem !== null)
            ->groupBy('work_item_id')
            ->map(fn (Collection $baris) => [
                'uraian' => $baris->first()->workItem->uraian_pekerjaan,
                'satuan' => $baris->first()->workItem->unit?->code,
                'volume' => round((float) $baris->sum('volume_realisasi'), 3),
                'bobot' => round((float) $baris->sum('bobot_realisasi'), 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int,ProgressReport>  $laporan
     * @return list<array<string,mixed>>
     */
    private function daftarKendala(Collection $laporan): array
    {
        return $laporan->flatMap(fn ($r) => $r->issues->map(fn ($i) => [
            'tanggal_laporan' => $r->tanggal_laporan->toDateString(),
            'nama_periode' => $r->period?->nama_periode,
            'jenis_kendala' => $i->jenis_kendala,
            'pekerjaan' => $i->workItem?->uraian_pekerjaan,
            'deskripsi' => ProgressService::gabungKendala($i->deskripsi, $i->alasan_keterlambatan),
            'tindak_lanjut' => $i->tindak_lanjut,
            'status' => $i->status,
        ]))->values()->all();
    }

    /**
     * Kesimpulan pelaksanaan berdasarkan angka laporan. Proyek hanya disebut selesai bila status
     * proyek SELESAI dan realisasi kumulatif sudah mencapai total rencana.
     *
     * @return list<string>
     */
    private function kesimpulanAkhir(Project $project, Period $periodeTerakhir, array $r): array
    {
        $persen = fn (float $nilai) => number_format($nilai, 2, ',', '.').'%';
        $statusSelesai = $project->status === ProjectStatus::SELESAI;
        $progresPenuh = $r['total_rencana'] > 0 && $r['realisasi_kumulatif'] >= $r['total_rencana'] - 0.005;

        $hasil = [];

        if ($statusSelesai && $progresPenuh) {
            $hasil[] = 'Proyek telah selesai dilaksanakan dengan realisasi progres '.$persen($r['realisasi_kumulatif'])
                .' dari total rencana '.$persen($r['total_rencana']).'.';
        } else {
            $hasil[] = 'Proyek belum dinyatakan selesai. Realisasi progres sampai '.$periodeTerakhir->nama_periode
                .' sebesar '.$persen($r['realisasi_kumulatif']).' dari total rencana '.$persen($r['total_rencana'])
                .', sisa progres '.$persen($r['sisa_progres']).'.';

            if ($statusSelesai) {
                $hasil[] = 'Status proyek tercatat Selesai, namun realisasi progres yang dilaporkan belum mencapai total rencana sehingga perlu diverifikasi.';
            }
        }

        $hasil[] = match (true) {
            $r['deviasi'] < -0.005 => 'Realisasi tertinggal '.$persen(abs($r['deviasi'])).' dari rencana kumulatif '.$persen($r['rencana_kumulatif']).' pada periode laporan terakhir.',
            $r['deviasi'] > 0.005 => 'Realisasi lebih cepat '.$persen($r['deviasi']).' dari rencana kumulatif '.$persen($r['rencana_kumulatif']).' pada periode laporan terakhir.',
            default => 'Realisasi sesuai dengan rencana kumulatif '.$persen($r['rencana_kumulatif']).' pada periode laporan terakhir.',
        };

        $hasil[] = $r['jumlah_pekerjaan_selesai'].' dari '.$r['jumlah_pekerjaan'].' item pekerjaan telah mencapai volume kontrak.';

        if ($r['jumlah_kendala'] > 0) {
            $hasil[] = 'Tercatat '.$r['jumlah_kendala'].' kendala selama pelaksanaan, '.$r['jumlah_kendala_terbuka'].' di antaranya belum berstatus selesai.';
        }

        return $hasil;
    }

    /** Laporan milestone: target tahapan penting vs capaian progres. */
    public function milestone(Project $project): array
    {
        $kurva = $this->curve->build($project);

        return [
            'header' => $this->header($project),
            'milestone' => $this->milestoneRows($project, $kurva),
            'kurva' => $kurva,
        ];
    }

    /** Capaian tiap milestone dibandingkan realisasi kumulatif Kurva S pada periodenya. */
    private function milestoneRows(Project $project, array $kurva): array
    {
        $titikPerPeriode = collect($kurva['titik'])->keyBy('period_id');
        $periodeMilestone = collect($kurva['milestones'])->pluck('period_id', 'id');

        return $project->milestones()->get()->map(function ($m) use ($titikPerPeriode, $periodeMilestone) {
            $titik = $titikPerPeriode->get($periodeMilestone[$m->id] ?? null);
            $aktual = $titik['aktual_kumulatif'] ?? null;

            return [
                'id' => $m->id,
                'nama' => $m->nama,
                'deskripsi' => $m->deskripsi,
                'periode' => $titik['nama_periode'] ?? null,
                'tanggal_target' => $m->tanggal_target->toDateString(),
                'target_persentase' => (float) $m->target_persentase,
                'realisasi_persentase' => $aktual,
                'deviasi' => $aktual === null ? null : round($aktual - (float) $m->target_persentase, 4),
                'status' => $m->status,
            ];
        })->all();
    }

    /**
     * Susun baris pekerjaan per kategori beserta subtotal.
     *
     * @param  callable(WorkItem):array  $extra  kolom tambahan spesifik jenis laporan
     */
    private function groupedRows(Project $project, callable $extra): array
    {
        $items = $project->workItems()->with(['unit', 'category'])->orderBy('urutan')->get();
        $categories = $project->workCategories()->get();

        $hasil = [];
        $nomor = 0;

        $grup = $items->groupBy(fn (WorkItem $item) => $item->work_category_id ?? 0);

        $urutanKategori = $categories->pluck('id')->push(0)->unique();

        foreach ($urutanKategori as $categoryId) {
            $daftar = $grup->get($categoryId, collect());

            if ($daftar->isEmpty()) {
                continue;
            }

            $category = $categories->firstWhere('id', $categoryId);
            $baris = [];
            $urut = 0;

            foreach ($daftar as $item) {
                $nomor++;
                $urut++;

                $baris[] = array_merge([
                    'no' => $urut,
                    'no_global' => $nomor,
                    'work_item_id' => $item->id,
                    'uraian' => $item->uraian_pekerjaan,
                    'satuan' => $item->unit?->code,
                    'volume' => (float) $item->volume,
                    'harga_satuan' => $item->harga_satuan !== null ? (float) $item->harga_satuan : null,
                    'harga_pekerjaan' => $item->harga_pekerjaan !== null ? (float) $item->harga_pekerjaan : null,
                    'bobot' => (float) $item->bobot,
                ], $extra($item));
            }

            $hasil[] = [
                'id' => $category?->id,
                'kode' => $category?->kode ?? '-',
                'nama' => $category?->nama ?? 'PEKERJAAN LAINNYA',
                'items' => $baris,
                'subtotal' => $this->subtotal($baris),
            ];
        }

        return $hasil;
    }

    /** Subtotal satu kategori (harga, bobot, dan seluruh kolom realisasi bertipe volume/bobot). */
    private function subtotal(array $baris): array
    {
        $koleksi = collect($baris);
        $subtotal = [
            'harga_pekerjaan' => round((float) $koleksi->sum('harga_pekerjaan'), 2),
            'bobot' => round((float) $koleksi->sum('bobot'), 4),
        ];

        foreach (['rencana_sd', 'realisasi_lalu', 'realisasi_ini', 'realisasi_sd', 'realisasi_bulan_lalu', 'realisasi_bulan_ini', 'realisasi_sd_bulan_ini'] as $kolom) {
            if ($koleksi->first() && array_key_exists($kolom, $koleksi->first())) {
                $subtotal[$kolom] = [
                    'bobot' => round((float) $koleksi->sum(fn ($r) => $r[$kolom]['bobot']), 4),
                ];
            }
        }

        if ($koleksi->first() && array_key_exists('jadwal', $koleksi->first())) {
            $jumlahKolom = count($koleksi->first()['jadwal']);
            $jadwal = [];

            for ($i = 0; $i < $jumlahKolom; $i++) {
                $jadwal[] = [
                    'period_id' => $koleksi->first()['jadwal'][$i]['period_id'],
                    'bobot' => round((float) $koleksi->sum(fn ($r) => $r['jadwal'][$i]['bobot']), 4),
                ];
            }

            $subtotal['jadwal'] = $jadwal;
        }

        return $subtotal;
    }

    /** Total seluruh kategori. */
    private function totalOf(array $kategori, array $kolomRealisasi): array
    {
        $semua = collect($kategori)->flatMap(fn ($k) => $k['items']);

        $total = [
            'harga_pekerjaan' => round((float) $semua->sum('harga_pekerjaan'), 2),
            'bobot' => round((float) $semua->sum('bobot'), 4),
        ];

        foreach ($kolomRealisasi as $kolom) {
            $total[$kolom] = [
                'bobot' => round((float) $semua->sum(fn ($r) => $r[$kolom]['bobot'] ?? 0), 4),
            ];
        }

        if ($semua->first() && array_key_exists('jadwal', $semua->first())) {
            $jumlahKolom = count($semua->first()['jadwal']);
            $jadwal = [];

            for ($i = 0; $i < $jumlahKolom; $i++) {
                $jadwal[] = [
                    'period_id' => $semua->first()['jadwal'][$i]['period_id'],
                    'bobot' => round((float) $semua->sum(fn ($r) => $r['jadwal'][$i]['bobot']), 4),
                ];
            }

            $total['jadwal'] = $jadwal;
        }

        return $total;
    }

    /** @return Collection<int,Period> */
    public function periodsOfMonth(Project $project, int $bulanKe): Collection
    {
        return $project->periods()->where('bulan_ke', $bulanKe)->orderBy('urutan')->get();
    }
}
