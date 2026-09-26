<?php

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\ProgressDetail;
use App\Models\ProgressPhoto;
use App\Models\ProgressReport;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Menyimpan laporan progres harian QS beserta detail, foto, dan kendala. */
class ProgressService
{
    public function __construct(
        private readonly WeightCalculatorService $weights,
        private readonly ProjectScheduleService $schedule,
    ) {}

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $photos
     */
    public function create(Project $project, User $user, array $data, array $photos = []): ProgressReport
    {
        return DB::transaction(function () use ($project, $user, $data, $photos) {
            $period = $this->schedule->resolvePeriod($project, $data['tanggal_laporan']);

            $report = $project->progressReports()->create([
                'user_id' => $user->id,
                'period_id' => $period?->id,
                'tanggal_laporan' => $data['tanggal_laporan'],
                'keterangan' => $data['keterangan'] ?? null,
                'lokasi' => $data['lokasi'] ?? $project->lokasi,
                'cuaca' => $data['cuaca'] ?? null,
                'status' => $data['status'] ?? ReportStatus::DRAFT->value,
                'dikirim_pada' => ($data['status'] ?? null) === ReportStatus::DIKIRIM->value ? now() : null,
            ]);

            $this->syncDetails($report, $data['details'] ?? []);
            $this->syncIssues($report, $data['issues'] ?? []);
            $this->storePhotos($report, $photos, $data['photo_captions'] ?? []);

            return $report->fresh($this->relations());
        });
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,UploadedFile>  $photos
     */
    public function update(ProgressReport $report, array $data, array $photos = []): ProgressReport
    {
        return DB::transaction(function () use ($report, $data, $photos) {
            if (isset($data['tanggal_laporan'])) {
                $period = $this->schedule->resolvePeriod($report->project, $data['tanggal_laporan']);
                $report->period_id = $period?->id;
                $report->tanggal_laporan = $data['tanggal_laporan'];
            }

            $report->fill([
                'keterangan' => $data['keterangan'] ?? $report->keterangan,
                'lokasi' => $data['lokasi'] ?? $report->lokasi,
                'cuaca' => $data['cuaca'] ?? $report->cuaca,
            ]);

            if (isset($data['status'])) {
                $report->status = $data['status'];
                $report->dikirim_pada = $data['status'] === ReportStatus::DIKIRIM->value
                    ? ($report->dikirim_pada ?? now())
                    : null;
            }

            $report->save();

            if (array_key_exists('details', $data)) {
                $report->details()->delete();
                $this->syncDetails($report, $data['details']);
            }

            if (array_key_exists('issues', $data)) {
                $report->issues()->delete();
                $this->syncIssues($report, $data['issues']);
            }

            $this->storePhotos($report, $photos, $data['photo_captions'] ?? []);

            return $report->fresh($this->relations());
        });
    }

    public function submit(ProgressReport $report): ProgressReport
    {
        if ($report->details()->count() === 0) {
            throw ValidationException::withMessages([
                'details' => 'Laporan tidak dapat dikirim karena belum memiliki detail pekerjaan.',
            ]);
        }

        $report->forceFill([
            'status' => ReportStatus::DIKIRIM->value,
            'dikirim_pada' => now(),
        ])->save();

        return $report->fresh($this->relations());
    }

    public function deletePhoto(ProgressPhoto $photo): void
    {
        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();
    }

    public function deleteReport(ProgressReport $report): void
    {
        DB::transaction(function () use ($report) {
            foreach ($report->photos as $photo) {
                Storage::disk('public')->delete($photo->file_path);
            }

            $report->delete();
        });
    }

    /** @return array<int,string> */
    private function relations(): array
    {
        return ['details.workItem.unit', 'photos', 'issues.workItem', 'period', 'user', 'project'];
    }

    /** @param array<int,array<string,mixed>> $details */
    private function syncDetails(ProgressReport $report, array $details): void
    {
        foreach ($details as $row) {
            /** @var WorkItem $item */
            $item = WorkItem::where('project_id', $report->project_id)->findOrFail($row['work_item_id']);

            $volume = (float) $row['volume_realisasi'];
            $this->assertVolumeTidakMelebihiRencana($report, $item, $volume);

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

    /** Realisasi kumulatif sebuah pekerjaan tidak boleh melebihi volume rencananya. */
    private function assertVolumeTidakMelebihiRencana(ProgressReport $report, WorkItem $item, float $volume): void
    {
        $sudahTerealisasi = (float) ProgressDetail::query()
            ->where('work_item_id', $item->id)
            ->where('progress_report_id', '!=', $report->id)
            ->sum('volume_realisasi');

        $sisa = round((float) $item->volume - $sudahTerealisasi, 3);

        if ($volume > $sisa + 0.0001) {
            throw ValidationException::withMessages([
                'details' => 'Volume realisasi pekerjaan "'.$item->uraian_pekerjaan.'" melebihi sisa volume rencana (sisa '.number_format($sisa, 2, ',', '.').').',
            ]);
        }
    }

    /** @param array<int,array<string,mixed>> $issues */
    private function syncIssues(ProgressReport $report, array $issues): void
    {
        foreach ($issues as $row) {
            $report->issues()->create([
                'work_item_id' => $row['work_item_id'] ?? null,
                'jenis_kendala' => $row['jenis_kendala'] ?? 'LAINNYA',
                'deskripsi' => $row['deskripsi'],
                'alasan_keterlambatan' => $row['alasan_keterlambatan'] ?? null,
                'tindak_lanjut' => $row['tindak_lanjut'] ?? null,
                'status' => $row['status'] ?? 'TERBUKA',
            ]);
        }
    }

    /**
     * @param  array<int,UploadedFile>  $photos
     * @param  array<int,string>  $captions
     */
    private function storePhotos(ProgressReport $report, array $photos, array $captions = []): void
    {
        foreach ($photos as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $path = $file->store('progress/'.$report->project_id.'/'.$report->id, 'public');

            ProgressPhoto::create([
                'progress_report_id' => $report->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'caption' => $captions[$index] ?? null,
                'diunggah_pada' => now(),
            ]);
        }
    }
}
