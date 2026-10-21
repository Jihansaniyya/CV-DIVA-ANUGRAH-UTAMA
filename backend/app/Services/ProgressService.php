<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\Period;
use App\Models\ProgressDetail;
use App\Models\ProgressPhoto;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Menyimpan laporan progres harian QS beserta detail, foto, dan kendala.
 *
 * Aturan yang dijaga di sini (bukan hanya di form frontend):
 *   - sisa volume   = volume pekerjaan - volume laporan yang memakai volume (terkirim + draf QS aktif);
 *   - satu pekerjaan hanya boleh tercatat sekali per tanggal pada satu proyek;
 *   - tanggal progres berada dalam jadwal proyek dan tidak melewati hari ini;
 *   - laporan baru boleh berstatus DIKIRIM bila lokasi, keterangan, dan minimal satu foto per pekerjaan terisi;
 *   - proyek berstatus Selesai tidak menerima progres lagi, dan pekerjaan yang sisa volumenya habis tidak dapat dipilih.
 *
 * Setiap penyimpanan mengunci baris pekerjaan proyek lebih dulu sehingga dua permintaan bersamaan
 * tidak dapat memakai sisa volume yang sama.
 */
class ProgressService
{
    private const TOLERANSI_VOLUME = 0.0001;

    public function __construct(
        private readonly WeightCalculatorService $weights,
        private readonly ProjectScheduleService $schedule,
        private readonly ProjectStatusService $status,
        private readonly ImageService $images,
        private readonly WorkPlanService $plans,
    ) {}

    /**
     * Simpan laporan baru. Bila `client_request_id` sudah pernah dipakai QS yang sama untuk proyek ini,
     * laporan yang sudah ada dikembalikan tanpa membuat laporan baru.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $photos
     * @return array{report: ProgressReport, baru: bool}
     */
    public function create(Project $project, User $user, array $data, array $photos = []): array
    {
        $kunci = $data['client_request_id'] ?? null;

        if ($kunci !== null && ($ada = $this->permintaanSebelumnya($project, $user, $kunci)) !== null) {
            return ['report' => $ada, 'baru' => false];
        }

        $this->assertProyekBelumSelesai($project);
        $this->assertRencanaLengkap($project);

        $status = $data['status'] ?? ReportStatus::DRAFT->value;
        $lokasi = filled($data['lokasi'] ?? null) ? $data['lokasi'] : $project->lokasi;
        $photos = array_values(array_filter($photos, fn ($f) => $f instanceof UploadedFile));

        if ($status === ReportStatus::DIKIRIM->value) {
            $this->assertBuktiLengkap($lokasi, $data['keterangan'] ?? null, count($photos));
        }

        $berkas = [];

        try {
            $report = DB::transaction(function () use ($project, $user, $data, $photos, $status, $lokasi, $kunci, &$berkas) {
                $this->kunciPekerjaan($project->id);
                $period = $this->periodeLaporan($project, $data['tanggal_laporan']);

                $report = $project->progressReports()->create([
                    'user_id' => $user->id,
                    'period_id' => $period->id,
                    'tanggal_laporan' => $data['tanggal_laporan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'lokasi' => $lokasi,
                    'cuaca' => $data['cuaca'] ?? null,
                    'status' => $status,
                    'dikirim_pada' => $status === ReportStatus::DIKIRIM->value ? now() : null,
                    'client_request_id' => $kunci,
                ]);

                $this->syncDetails($report, $data['details'] ?? []);
                $this->assertRealisasiValid($report);
                $this->syncIssues($report, $data['issues'] ?? []);

                if ($status === ReportStatus::DIKIRIM->value) {
                    $this->assertKendalaLengkap($report);
                }

                $this->storePhotos($report, $photos, $data['photo_captions'] ?? [], $data['photo_work_item_ids'] ?? [], $berkas);

                if ($status === ReportStatus::DIKIRIM->value) {
                    $this->assertFotoPerPekerjaan($report);
                }

                return $report->fresh($this->relations());
            });
        } catch (UniqueConstraintViolationException $e) {
            // Permintaan yang sama tiba bersamaan: yang kalah dibatalkan dan memakai laporan pemenang.
            $this->hapusBerkas($berkas);

            if ($kunci !== null && ($ada = $this->permintaanSebelumnya($project, $user, $kunci)) !== null) {
                return ['report' => $ada, 'baru' => false];
            }

            throw $e;
        } catch (Throwable $e) {
            $this->hapusBerkas($berkas);

            throw $e;
        }

        $this->status->sinkronkan($project);

        return ['report' => $report, 'baru' => true];
    }

    /**
     * Ubah draf. Bila `status` = DIKIRIM, draf sekaligus dikirim setelah lolos pemeriksaan bukti.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $photos
     */
    public function update(ProgressReport $report, array $data, array $photos = []): ProgressReport
    {
        $photos = array_values(array_filter($photos, fn ($f) => $f instanceof UploadedFile));
        $berkas = [];

        try {
            $report = DB::transaction(function () use ($report, $data, $photos, &$berkas) {
                $this->kunciPekerjaan($report->project_id);
                $report->refresh();
                $this->assertMasihDraf($report);
                $this->assertProyekBelumSelesai($report->project);

                if (isset($data['tanggal_laporan'])) {
                    $period = $this->periodeLaporan($report->project, $data['tanggal_laporan']);
                    $report->period_id = $period->id;
                    $report->tanggal_laporan = $data['tanggal_laporan'];
                }

                if (array_key_exists('keterangan', $data)) {
                    $report->keterangan = $data['keterangan'];
                }

                if (array_key_exists('lokasi', $data)) {
                    $report->lokasi = filled($data['lokasi']) ? $data['lokasi'] : $report->project->lokasi;
                }

                if (array_key_exists('cuaca', $data)) {
                    $report->cuaca = $data['cuaca'];
                }

                $report->save();

                if (array_key_exists('details', $data)) {
                    // Detail dibuat ulang; catat pekerjaan pemilik setiap foto agar kaitannya dipulihkan setelahnya.
                    $pemilikFoto = $report->photos()->with('detail:id,work_item_id')->get()
                        ->mapWithKeys(fn (ProgressPhoto $foto) => [$foto->id => $foto->detail?->work_item_id]);

                    $report->details()->delete();
                    $this->syncDetails($report, $data['details']);
                    $this->kaitkanUlangFoto($report, $pemilikFoto);
                }

                if (array_key_exists('issues', $data)) {
                    $report->issues()->delete();
                    $this->syncIssues($report, $data['issues'] ?? []);
                }

                $this->storePhotos($report, $photos, $data['photo_captions'] ?? [], $data['photo_work_item_ids'] ?? [], $berkas);
                $this->assertRealisasiValid($report);

                if (($data['status'] ?? null) === ReportStatus::DIKIRIM->value) {
                    $this->kirim($report);
                }

                return $report->fresh($this->relations());
            });
        } catch (Throwable $e) {
            $this->hapusBerkas($berkas);

            throw $e;
        }

        $this->status->sinkronkan($report->project);

        return $report;
    }

    /** Kirim draf sehingga ikut terhitung pada progres aktual dan Kurva S. */
    public function submit(ProgressReport $report): ProgressReport
    {
        $report = DB::transaction(function () use ($report) {
            $this->kunciPekerjaan($report->project_id);
            $report->refresh();
            $this->assertMasihDraf($report);
            $this->assertProyekBelumSelesai($report->project);
            $this->assertRealisasiValid($report);
            $this->kirim($report);

            return $report->fresh($this->relations());
        });

        $this->status->sinkronkan($report->project);

        return $report;
    }

    public function deletePhoto(ProgressPhoto $photo): void
    {
        $path = $photo->file_path;

        DB::transaction(fn () => $photo->delete());

        // Berkas dihapus setelah baris database terhapus agar tidak ada foto tercatat tanpa berkas.
        Storage::disk('public')->delete($path);
    }

    public function deleteReport(ProgressReport $report): void
    {
        $paths = $report->photos()->pluck('file_path')->all();
        $folder = 'progress/'.$report->project_id.'/'.$report->id;

        DB::transaction(fn () => $report->delete());

        $disk = Storage::disk('public');
        $disk->delete($paths);

        if ($disk->exists($folder) && $disk->allFiles($folder) === []) {
            $disk->deleteDirectory($folder);
        }

        $this->status->sinkronkan($report->project);
    }

    /**
     * Volume yang sudah dipakai per pekerjaan: laporan terkirim + draf QS yang masih ditugaskan.
     * Sumber tunggal "sisa volume" untuk form progres, dashboard QS, dan validasi backend.
     *
     * @param  iterable<int>  $workItemIds
     * @return Collection<int,float>
     */
    public function volumeTerpakai(iterable $workItemIds, ?int $kecualiLaporanId = null): Collection
    {
        return ProgressDetail::query()
            ->whereIn('work_item_id', collect($workItemIds))
            ->whereHas('report', fn ($q) => $q->memakaiVolume()
                ->when($kecualiLaporanId, fn ($q) => $q->where('progress_reports.id', '!=', $kecualiLaporanId)))
            ->selectRaw('work_item_id, SUM(volume_realisasi) as total')
            ->groupBy('work_item_id')
            ->pluck('total', 'work_item_id')
            ->map(fn ($total) => (float) $total);
    }

    /** "Hari ini" menurut zona waktu lokasi proyek, bukan zona waktu server (UTC). */
    public static function hariIni(): CarbonImmutable
    {
        return ZonaWaktu::hariIni();
    }

    private function permintaanSebelumnya(Project $project, User $user, string $kunci): ?ProgressReport
    {
        $ada = ProgressReport::where('user_id', $user->id)->where('client_request_id', $kunci)->first();

        if ($ada !== null && $ada->project_id !== $project->id) {
            throw ValidationException::withMessages([
                'client_request_id' => 'Identitas permintaan sudah dipakai untuk laporan proyek lain. Muat ulang form lalu coba lagi.',
            ]);
        }

        return $ada?->load($this->relations());
    }

    /**
     * Kunci baris pekerjaan proyek sampai transaksi selesai. Wajib menjadi query pertama di dalam
     * transaksi agar perhitungan sisa volume membaca data terbaru setelah permintaan lain selesai.
     */
    private function kunciPekerjaan(int $projectId): void
    {
        WorkItem::query()->where('project_id', $projectId)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function kirim(ProgressReport $report): void
    {
        if ($report->details()->count() === 0) {
            throw ValidationException::withMessages([
                'details' => 'Laporan tidak dapat dikirim karena belum memiliki detail pekerjaan.',
            ]);
        }

        $this->assertRencanaLengkap($report->project);
        $this->assertTanggalTidakDiMasaDepan($report->tanggal_laporan->toDateString());
        $this->assertBuktiLengkap($report->lokasi, $report->keterangan, $report->photos()->count());
        $this->assertFotoPerPekerjaan($report);
        $this->assertKendalaLengkap($report);

        $report->forceFill([
            'status' => ReportStatus::DIKIRIM->value,
            'dikirim_pada' => now(),
        ])->save();
    }

    private function assertMasihDraf(ProgressReport $report): void
    {
        if ($report->status !== ReportStatus::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Laporan ini sudah dikirim sehingga tidak dapat diubah atau dikirim ulang.',
            ]);
        }
    }

    /** Proyek yang seluruh pekerjaannya sudah terealisasi 100% (status Selesai) tidak menerima progres lagi. */
    private function assertProyekBelumSelesai(Project $project): void
    {
        if ($project->status === ProjectStatus::SELESAI) {
            throw ValidationException::withMessages([
                'project_id' => 'Proyek ini sudah selesai sehingga progres harian tidak dapat ditambahkan atau dikirim lagi.',
            ]);
        }
    }

    /** Laporan yang dikirim wajib memiliki lokasi, keterangan kegiatan, dan minimal satu foto bukti. */
    private function assertBuktiLengkap(?string $lokasi, ?string $keterangan, int $jumlahFoto): void
    {
        $pesan = [];

        if (trim((string) $lokasi) === '') {
            $pesan['lokasi'] = 'Lokasi pekerjaan wajib diisi sebelum progres dikirim.';
        }

        if (trim((string) $keterangan) === '') {
            $pesan['keterangan'] = 'Keterangan kegiatan wajib diisi sebelum progres dikirim.';
        }

        if ($jumlahFoto < 1) {
            $pesan['photos'] = 'Minimal satu foto bukti pekerjaan wajib diunggah sebelum progres dikirim.';
        }

        if ($pesan !== []) {
            throw ValidationException::withMessages($pesan);
        }
    }

    /** Setiap pekerjaan yang dilaporkan wajib memiliki minimal satu foto bukti agar foto dapat ditelusuri ke progresnya. */
    private function assertFotoPerPekerjaan(ProgressReport $report): void
    {
        $tanpaFoto = $report->details()->whereDoesntHave('photos')->with('workItem')->get();

        if ($tanpaFoto->isNotEmpty()) {
            throw ValidationException::withMessages([
                'photos' => 'Foto bukti pekerjaan "'.$tanpaFoto->pluck('workItem.uraian_pekerjaan')->implode('", "')
                    .'" belum diunggah. Setiap pekerjaan wajib memiliki minimal satu foto sebelum progres dikirim.',
            ]);
        }
    }

    /**
     * Pilihan "Apakah pekerjaan sudah selesai?" pada form tidak disimpan tersendiri karena dapat diturunkan
     * dari data: laporan tanpa kendala = Selesai, laporan berkendala = Belum Selesai. Setiap kendala pada
     * laporan yang dikirim wajib lengkap seperti di form: pekerjaan terkait (salah satu pekerjaan yang
     * dilaporkan), jenis kendala, kendala & alasan keterlambatan, serta rencana tindak lanjut.
     */
    private function assertKendalaLengkap(ProgressReport $report): void
    {
        $pekerjaanDilaporkan = $report->details()->pluck('work_item_id')->all();

        foreach ($report->issues()->get() as $index => $kendala) {
            $nomor = $index + 1;
            $pesan = match (true) {
                $kendala->work_item_id === null || ! in_array($kendala->work_item_id, $pekerjaanDilaporkan, true) => "Kendala ke-{$nomor} harus terkait dengan pekerjaan yang dilaporkan pada progres ini.",
                trim((string) $kendala->jenis_kendala) === '' => "Jenis kendala ke-{$nomor} wajib dipilih.",
                trim((string) $kendala->deskripsi) === '' => "Kendala & alasan keterlambatan ke-{$nomor} wajib diisi.",
                trim((string) $kendala->tindak_lanjut) === '' => "Rencana tindak lanjut kendala ke-{$nomor} wajib diisi sebelum progres dikirim.",
                default => null,
            };

            if ($pesan !== null) {
                throw ValidationException::withMessages(['issues' => $pesan]);
            }
        }
    }

    private function assertTanggalTidakDiMasaDepan(string $tanggal): void
    {
        $hariIni = self::hariIni();

        // Dibandingkan sebagai tanggal kalender (Y-m-d) agar tidak terpengaruh selisih zona waktu.
        if (CarbonImmutable::parse($tanggal)->toDateString() > $hariIni->toDateString()) {
            throw ValidationException::withMessages([
                'tanggal_laporan' => 'Tanggal progres tidak boleh melewati hari ini ('.$hariIni->translatedFormat('d M Y')
                    .'). Progres hanya mencatat pekerjaan yang sudah dilaksanakan.',
            ]);
        }
    }

    /**
     * Tanggal laporan wajib berada di dalam salah satu periode (minggu) proyek dan tidak di masa depan.
     * Laporan di luar jadwal tidak masuk kurva S dan membuat progres terlihat tidak bertambah.
     */
    private function periodeLaporan(Project $project, string $tanggal): Period
    {
        $period = $this->schedule->resolvePeriod($project, $tanggal);

        if ($period === null) {
            throw ValidationException::withMessages([
                'tanggal_laporan' => 'Tanggal progres berada di luar jadwal proyek ('
                    .$project->tanggal_mulai->translatedFormat('d M Y').' s/d '
                    .$project->tanggal_selesai->translatedFormat('d M Y').').',
            ]);
        }

        $this->assertTanggalTidakDiMasaDepan($tanggal);

        return $period;
    }

    /** @return array<int,string> */
    private function relations(): array
    {
        return ['details.workItem.unit', 'photos.detail.workItem', 'issues.workItem', 'period', 'user', 'project'];
    }

    /** @param array<int,array<string,mixed>> $details */
    private function syncDetails(ProgressReport $report, array $details): void
    {
        foreach ($details as $row) {
            /** @var WorkItem $item */
            $item = WorkItem::where('project_id', $report->project_id)->find($row['work_item_id']);

            if ($item === null) {
                throw ValidationException::withMessages([
                    'details' => 'Pekerjaan yang dipilih bukan bagian dari proyek ini.',
                ]);
            }

            $volume = (float) $row['volume_realisasi'];
            $nilai = $this->weights->actualValues($item, $volume);

            $report->details()->create([
                'work_item_id' => $item->id,
                'volume_realisasi' => $volume,
                'persentase_realisasi' => $nilai['persentase_realisasi'],
                'bobot_realisasi' => $nilai['bobot_realisasi'],
                'keterangan' => $row['keterangan'] ?? null,
            ]);
        }
    }

    /**
     * Periksa seluruh detail laporan terhadap laporan lain pada proyek yang sama:
     *   - pekerjaan yang sama tidak boleh tercatat dua kali pada tanggal yang sama (mencegah realisasi ganda);
     *   - volume tidak boleh melebihi sisa volume (volume - volume terpakai laporan lain).
     */
    private function assertRealisasiValid(ProgressReport $report): void
    {
        $details = $report->details()->with('workItem')->get();

        if ($details->isEmpty()) {
            return;
        }

        $ids = $details->pluck('work_item_id')->unique()->values();

        if ($ids->count() !== $details->count()) {
            throw ValidationException::withMessages([
                'details' => 'Pekerjaan yang sama tidak boleh diinput dua kali dalam satu progres.',
            ]);
        }

        $ganda = ProgressDetail::query()
            ->whereIn('work_item_id', $ids)
            ->whereHas('report', fn ($q) => $q->memakaiVolume()
                ->where('progress_reports.project_id', $report->project_id)
                ->whereDate('progress_reports.tanggal_laporan', $report->tanggal_laporan->toDateString())
                ->where('progress_reports.id', '!=', $report->id))
            ->with('report')
            ->get()
            ->keyBy('work_item_id');

        $terpakai = $this->volumeTerpakai($ids, $report->id);
        $tanggal = $report->tanggal_laporan->translatedFormat('d M Y');

        foreach ($details as $detail) {
            $item = $detail->workItem;

            if ($lain = $ganda->get($item->id)) {
                $pesan = $lain->report->status === ReportStatus::DRAFT
                    ? 'Pekerjaan "'.$item->uraian_pekerjaan.'" sudah tercatat pada draf progres tanggal '.$tanggal
                        .'. Buka dan lengkapi draf tersebut daripada membuat progres baru.'
                    : 'Pekerjaan "'.$item->uraian_pekerjaan.'" sudah dilaporkan pada tanggal '.$tanggal
                        .'. Progres yang sama tidak dapat dicatat dua kali.';

                throw ValidationException::withMessages(['details' => $pesan]);
            }

            $sisa = round((float) $item->volume - (float) ($terpakai[$item->id] ?? 0), 3);

            if ($sisa <= self::TOLERANSI_VOLUME) {
                throw ValidationException::withMessages([
                    'details' => 'Pekerjaan "'.$item->uraian_pekerjaan.'" sudah selesai (sisa volume 0,00, sudah termasuk draf yang belum dikirim)'
                        .' sehingga tidak dapat dipilih lagi.',
                ]);
            }

            if ((float) $detail->volume_realisasi > $sisa + self::TOLERANSI_VOLUME) {
                throw ValidationException::withMessages([
                    'details' => 'Volume realisasi pekerjaan "'.$item->uraian_pekerjaan.'" melebihi sisa volume (sisa '
                        .number_format(max($sisa, 0), 2, ',', '.').', sudah termasuk draf yang belum dikirim).',
                ]);
            }
        }
    }

    /** Progres baru hanya dapat ditambahkan setelah total bobot rencana pekerjaan proyek mencapai 100%. */
    private function assertRencanaLengkap(Project $project): void
    {
        if ($this->plans->rencanaLengkap($project)) {
            return;
        }

        $total = number_format($this->plans->totalBobotRencana($project), 2, ',', '.');

        throw ValidationException::withMessages([
            'project_id' => "Progres belum dapat ditambahkan karena rencana pekerjaan proyek baru dijadwalkan {$total}% dari total bobot pekerjaan. Rencana harus mencapai 100% terlebih dahulu.",
        ]);
    }

    /**
     * Kendala dan alasan keterlambatan ditampilkan sebagai satu narasi. Data lama yang masih
     * menyimpan alasan terpisah digabungkan ke belakang deskripsi kendalanya.
     */
    public static function gabungKendala(?string $deskripsi, ?string $alasan): string
    {
        $deskripsi = trim((string) $deskripsi);
        $alasan = trim((string) $alasan);

        if ($alasan === '' || $alasan === $deskripsi) {
            return $deskripsi;
        }

        if ($deskripsi === '') {
            return $alasan;
        }

        return rtrim($deskripsi, " \t\n\r\0\x0B.").'. '.$alasan;
    }

    /** @param array<int,array<string,mixed>> $issues */
    private function syncIssues(ProgressReport $report, array $issues): void
    {
        foreach ($issues as $row) {
            $workItemId = $row['work_item_id'] ?? null;

            if ($workItemId !== null && ! WorkItem::where('project_id', $report->project_id)->whereKey($workItemId)->exists()) {
                throw ValidationException::withMessages([
                    'issues' => 'Pekerjaan terkait kendala bukan bagian dari proyek ini.',
                ]);
            }

            $report->issues()->create([
                'work_item_id' => $workItemId,
                // Kosong bila belum dipilih (draf); laporan yang dikirim wajib memiliki jenis kendala.
                'jenis_kendala' => $row['jenis_kendala'] ?? null,
                // Alasan keterlambatan tidak lagi disimpan terpisah; digabung ke deskripsi kendala.
                'deskripsi' => self::gabungKendala($row['deskripsi'], $row['alasan_keterlambatan'] ?? null),
                'alasan_keterlambatan' => null,
                'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                'status' => $row['status'] ?? 'TERBUKA',
            ]);
        }
    }

    /**
     * Pasangkan kembali foto ke detail baru dengan pekerjaan yang sama. Foto yang pekerjaannya tidak lagi
     * dilaporkan menjadi tidak terkait (progress_detail_id null).
     *
     * @param  Collection<int,int|null>  $pemilikFoto  photo id => work_item_id sebelum detail dibuat ulang
     */
    private function kaitkanUlangFoto(ProgressReport $report, Collection $pemilikFoto): void
    {
        $detailPerPekerjaan = $report->details()->pluck('id', 'work_item_id');

        foreach ($pemilikFoto->filter() as $photoId => $workItemId) {
            ProgressPhoto::whereKey($photoId)->update(['progress_detail_id' => $detailPerPekerjaan[$workItemId] ?? null]);
        }
    }

    /**
     * @param  array<int,UploadedFile>  $photos
     * @param  array<int,string|null>  $captions
     * @param  array<int,int|null>  $pekerjaanFoto  work_item_id pemilik setiap foto (indeks sama dengan $photos)
     * @param  array<int,string>  $berkas  path berkas yang sudah ditulis, untuk dibersihkan bila transaksi gagal
     */
    private function storePhotos(ProgressReport $report, array $photos, array $captions, array $pekerjaanFoto, array &$berkas): void
    {
        $detailPerPekerjaan = $report->details()->pluck('id', 'work_item_id');

        foreach ($photos as $index => $file) {
            $workItemId = isset($pekerjaanFoto[$index]) ? (int) $pekerjaanFoto[$index] : null;

            if ($workItemId !== null && ! $detailPerPekerjaan->has($workItemId)) {
                throw ValidationException::withMessages([
                    'photos' => 'Foto harus terkait dengan pekerjaan yang dilaporkan pada progres ini.',
                ]);
            }

            // Permintaan tanpa pasangan pekerjaan: foto menjadi milik pekerjaan tunggal laporan, bila hanya ada satu.
            $detailId = $workItemId !== null
                ? $detailPerPekerjaan[$workItemId]
                : ($detailPerPekerjaan->count() === 1 ? $detailPerPekerjaan->first() : null);

            $path = $file->store('progress/'.$report->project_id.'/'.$report->id, 'public');
            $berkas[] = $path;
            $path = $this->kompresFoto($path);
            $berkas[] = $path;

            ProgressPhoto::create([
                'progress_report_id' => $report->id,
                'progress_detail_id' => $detailId,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => Storage::disk('public')->size($path),
                'caption' => $captions[$index] ?? null,
                'diunggah_pada' => now(),
            ]);
        }
    }

    /** @param array<int,string> $paths */
    private function hapusBerkas(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete(array_unique($paths));
        }
    }

    /**
     * Perkecil foto yang tersimpan (sisi terpanjang 1600 px, JPEG). Mengembalikan path akhir;
     * bila foto sudah kecil atau tidak dapat diproses, berkas asli dipakai apa adanya.
     */
    public function kompresFoto(string $path): string
    {
        $disk = Storage::disk('public');
        $tujuan = preg_replace('/\.[^.\/]+$/', '', $path).'.jpg';
        $sementara = $disk->path($tujuan).'.tmp';

        if (! $this->images->perkecil($disk->path($path), $sementara)) {
            return $path;
        }

        if ($tujuan !== $path) {
            $disk->delete($path);
        }

        rename($sementara, $disk->path($tujuan));

        return $tujuan;
    }
}
