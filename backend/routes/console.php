<?php

use App\Models\ProgressPhoto;
use App\Services\ProgressService;
use App\Services\ProjectStatusService;
use App\Services\ReportDocumentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('proyek:sinkron-status', function (ProjectStatusService $status) {
    $this->info($status->sinkronkanSemua().' status proyek diperbarui.');
})->purpose('Hitung ulang status proyek dari jadwal dan realisasi');

Artisan::command('laporan:bersihkan', function (ReportDocumentService $documents) {
    $hasil = $documents->bersihkan();
    $this->info("{$hasil['duplikat']} dokumen duplikat dan {$hasil['yatim']} berkas tanpa riwayat dihapus.");
})->purpose('Hapus dokumen laporan duplikat dan berkas laporan yang tidak tercatat');

Artisan::command('foto:kompres', function (ProgressService $progress) {
    $diperkecil = 0;

    ProgressPhoto::query()->chunkById(100, function ($fotos) use ($progress, &$diperkecil) {
        foreach ($fotos as $foto) {
            if (! Storage::disk('public')->exists($foto->file_path)) {
                continue;
            }

            $path = $progress->kompresFoto($foto->file_path);
            $ukuran = Storage::disk('public')->size($path);

            if ($path !== $foto->file_path || $ukuran !== (int) $foto->file_size) {
                $foto->update(['file_path' => $path, 'file_size' => $ukuran]);
                $diperkecil++;
            }
        }
    });

    $this->info("{$diperkecil} foto progres diperkecil.");
})->purpose('Perkecil foto progres lama (sisi terpanjang 1600 px, JPEG)');

// Status berubah karena tanggal mulai/selesai terlewati; dijalankan setiap awal hari.
Schedule::command('proyek:sinkron-status')->dailyAt('00:05');
