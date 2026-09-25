<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Period;
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
            ->with(['user', 'period', 'details.workItem.unit', 'photos', 'materials.unit', 'issues.workItem'])
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
                'material' => $report->materials->map(fn ($m) => [
                    'nama_material' => $m->nama_material,
                    'jumlah' => (float) $m->jumlah,
                    'satuan' => $m->unit?->code ?? $m->satuan,
                    'keterangan' => $m->keterangan,
                ])->all(),
                'kendala' => $report->issues->map(fn ($i) => [
                    'jenis_kendala' => $i->jenis_kendala,
                    'pekerjaan' => $i->workItem?->uraian_pekerjaan,
                    'deskripsi' => $i->deskripsi,
                    'alasan_keterlambatan' => $i->alasan_keterlambatan,
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

        $realisasiSd = round((float) collect($kategori)->flatMap(fn ($k) => $k['items'])->sum(fn ($i) => $i['realisasi_sd']['bobot']), 4);

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
            'total' => $this->totalOf($kategori, ['realisasi_lalu', 'realisasi_ini', 'realisasi_sd']),
            'rekap' => [
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
                'realisasi_sd_bulan_ini' => $total['realisasi_sd_bulan_ini']['bobot'] ?? 0,
                'rencana_sd_bulan_ini' => round((float) $project->workPlans()
                    ->whereHas('period', fn ($q) => $q->where('bulan_ke', '<=', $bulanKe))
                    ->sum('target_bobot'), 4),
            ],
        ];
    }

    /** Laporan milestone: target tahapan penting vs capaian progres. */
    public function milestone(Project $project): array
    {
        $kurva = $this->curve->build($project);
        $titikPerPeriode = collect($kurva['titik'])->keyBy('period_id');

        return [
            'header' => $this->header($project),
            'milestone' => $project->milestones()->with('period')->get()->map(function ($m) use ($titikPerPeriode) {
                $titik = $m->period_id ? $titikPerPeriode->get($m->period_id) : null;
                $aktual = $titik['aktual_kumulatif'] ?? null;

                return [
                    'id' => $m->id,
                    'nama' => $m->nama,
                    'deskripsi' => $m->deskripsi,
                    'periode' => $m->period?->nama_periode,
                    'tanggal_target' => $m->tanggal_target->toDateString(),
                    'target_persentase' => (float) $m->target_persentase,
                    'realisasi_persentase' => $aktual,
                    'deviasi' => $aktual === null ? null : round($aktual - (float) $m->target_persentase, 4),
                    'status' => $m->status,
                ];
            })->all(),
            'kurva' => $kurva,
        ];
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

        foreach (['realisasi_lalu', 'realisasi_ini', 'realisasi_sd', 'realisasi_bulan_lalu', 'realisasi_bulan_ini', 'realisasi_sd_bulan_ini'] as $kolom) {
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
