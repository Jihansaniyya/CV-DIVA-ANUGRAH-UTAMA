<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ReportDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Riwayat dokumen laporan yang digenerate.
 *
 * Laporan yang sama (proyek, tipe, format, dan rentang periode sama) yang diekspor ulang menggantikan
 * dokumen sebelumnya sehingga berkas di storage tidak menumpuk setiap kali tombol export ditekan.
 */
class ReportDocumentService
{
    private const FOLDER = 'reports';

    public function catat(
        Project $project,
        User $user,
        string $tipe,
        string $format,
        string $filePath,
        string $fileName,
        ?string $periodeMulai,
        ?string $periodeSelesai,
    ): ReportDocument {
        return DB::transaction(function () use ($project, $user, $tipe, $format, $filePath, $fileName, $periodeMulai, $periodeSelesai) {
            $lama = ReportDocument::query()
                ->where('project_id', $project->id)
                ->where('tipe_laporan', $tipe)
                ->where('format', $format)
                ->where(fn ($q) => $periodeMulai === null ? $q->whereNull('periode_mulai') : $q->whereDate('periode_mulai', $periodeMulai))
                ->where(fn ($q) => $periodeSelesai === null ? $q->whereNull('periode_selesai') : $q->whereDate('periode_selesai', $periodeSelesai))
                ->get();

            foreach ($lama as $dokumen) {
                if ($dokumen->file_path !== $filePath) {
                    Storage::disk('public')->delete($dokumen->file_path);
                }

                $dokumen->delete();
            }

            return ReportDocument::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'tipe_laporan' => $tipe,
                'format' => $format,
                'periode_mulai' => $periodeMulai,
                'periode_selesai' => $periodeSelesai,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'digenerate_pada' => now(),
            ]);
        });
    }

    /**
     * Rapikan storage: hapus dokumen duplikat (sisakan yang terbaru per laporan) dan berkas di folder
     * laporan yang tidak lagi tercatat di riwayat.
     *
     * @return array{duplikat:int,yatim:int}
     */
    public function bersihkan(): array
    {
        $duplikat = 0;

        ReportDocument::query()
            ->orderByDesc('digenerate_pada')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (ReportDocument $d) => implode('|', [
                $d->project_id,
                $d->tipe_laporan->value,
                $d->format,
                $d->periode_mulai?->toDateString(),
                $d->periode_selesai?->toDateString(),
            ]))
            ->each(function ($grup) use (&$duplikat) {
                foreach ($grup->slice(1) as $dokumen) {
                    Storage::disk('public')->delete($dokumen->file_path);
                    $dokumen->delete();
                    $duplikat++;
                }
            });

        $tercatat = ReportDocument::query()->pluck('file_path')->flip();
        $yatim = 0;

        foreach (Storage::disk('public')->allFiles(self::FOLDER) as $berkas) {
            if (! $tercatat->has($berkas)) {
                Storage::disk('public')->delete($berkas);
                $yatim++;
            }
        }

        return ['duplikat' => $duplikat, 'yatim' => $yatim];
    }
}
