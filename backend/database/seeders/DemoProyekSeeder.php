<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\ProjectScheduleService;
use App\Services\WeightCalculatorService;
use App\Services\WorkPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk melihat tampilan dashboard saat proyek sudah banyak.
 *
 * Tidak dipanggil oleh DatabaseSeeder. Jalankan manual:
 *   php artisan db:seed --class=DemoProyekSeeder
 *
 * Semua proyek demo memakai nomor SPK berawalan "DEMO/" sehingga mudah dihapus:
 *   Project::withTrashed()->where('nomor_spk', 'like', 'DEMO/%')->forceDelete();
 *   (forceDelete karena Project memakai soft delete; relasi ikut terhapus via cascade)
 */
class DemoProyekSeeder extends Seeder
{
    /**
     * [nama, lokasi, status, mulai (minggu dari sekarang), durasi (minggu), faktor realisasi]
     * Faktor realisasi < 1 membuat proyek tertinggal dari rencana.
     */
    private const PROYEK = [
        ['Peningkatan Jalan Lingkungan RT 05 Kelurahan Belimbing', 'RT. 05 Kel. Belimbing', 'BERJALAN', -3, 8, 1.00],
        ['Pembangunan Drainase Jl. Ahmad Yani', 'Jl. Ahmad Yani, Bontang Utara', 'BERJALAN', -5, 10, 0.85],
        ['Rehabilitasi Gedung Posyandu Kelurahan Tanjung Laut', 'Kel. Tanjung Laut, Bontang Selatan', 'BERJALAN', -2, 6, 0.95],
        ['Pembuatan Box Culvert RT 21 Kelurahan Gunung Telihan', 'RT. 21 Kel. Gunung Telihan', 'BERJALAN', -6, 12, 0.70],
        ['Peningkatan Jalan Usaha Tani Kelurahan Bontang Lestari', 'Kel. Bontang Lestari', 'BERJALAN', -4, 8, 1.00],
        ['Pembangunan Turap Sungai Kelurahan Loktuan', 'Kel. Loktuan, Bontang Utara', 'BERJALAN', -7, 12, 0.90],
        ['Pemasangan Paving Block Halaman Kantor Kelurahan Satimpo', 'Kel. Satimpo, Bontang Selatan', 'BERJALAN', -1, 4, 0.80],
        ['Normalisasi Saluran Primer Kelurahan Api-Api', 'Kel. Api-Api, Bontang Utara', 'BERJALAN', -5, 8, 0.75],
        ['Pembangunan Pagar Sekolah SDN 012 Bontang Barat', 'Kel. Kanaan, Bontang Barat', 'BERJALAN', -3, 6, 1.00],
        ['Peningkatan Jalan Setapak RT 17 Kelurahan Berbas Pantai', 'RT. 17 Kel. Berbas Pantai', 'BERJALAN', -2, 8, 0.90],
        ['Pembangunan Jembatan Kayu Ulin RT 08 Kelurahan Tanjung Laut Indah', 'RT. 08 Kel. Tanjung Laut Indah', 'TERLAMBAT', -10, 8, 0.80],
        ['Rehabilitasi Saluran Tersier RT 14 Kelurahan Guntung', 'RT. 14 Kel. Guntung', 'SELESAI', -16, 6, 1.00],
        ['Pembangunan Gapura Kelurahan Bontang Kuala', 'Kel. Bontang Kuala', 'SELESAI', -14, 4, 1.00],
        ['Pengaspalan Jalan Lingkungan RT 03 Kelurahan Telihan', 'RT. 03 Kel. Telihan', 'SELESAI', -20, 8, 1.00],
        ['Pembangunan Rumah Pompa Kelurahan Berbas Tengah', 'Kel. Berbas Tengah', 'BELUM_DIMULAI', 1, 10, 0],
        ['Pembangunan Drainase RT 11 Kelurahan Kanaan', 'RT. 11 Kel. Kanaan', 'BELUM_DIMULAI', 2, 6, 0],
    ];

    /** [uraian, satuan, volume, harga satuan] */
    private const PEKERJAAN = [
        ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
        ['Galian Tanah Biasa', 'm3', 120.00, 96500.00],
        ['Pek. Beton K-225', 'm3', 64.00, 1985000.00],
        ['Pek. Bekisting', 'm2', 96.00, 298500.00],
        ['Pek. Pembesian Polos', 'kg', 1400.00, 25096.50],
    ];

    public function __construct(
        private readonly ProjectScheduleService $schedule,
        private readonly WeightCalculatorService $weights,
        private readonly WorkPlanService $plans,
        private readonly ProgressService $progress,
    ) {}

    public function run(): void
    {
        $admin = User::where('email', 'admin@divaanugrahutama.co.id')->firstOrFail();
        $qs = User::whereIn('email', ['nisa@divaanugrahutama.co.id', 'jihan@divaanugrahutama.co.id'])->get()->values();
        $units = Unit::pluck('id', 'code');
        $hariIni = CarbonImmutable::now()->startOfDay();

        foreach (self::PROYEK as $index => [$nama, $lokasi, $status, $mulaiMinggu, $durasiMinggu, $faktor]) {
            // Geser hari mulai agar tanggal laporan antarproyek tidak sama persis.
            $mulai = $hariIni->startOfWeek()->addWeeks($mulaiMinggu)->addDays($index % 5);
            $selesai = $mulai->addWeeks($durasiMinggu)->subDay();
            $petugas = $qs[$index % $qs->count()];

            $project = Project::updateOrCreate(
                ['nomor_spk' => sprintf('DEMO/%03d/SPK/%d', $index + 1, $mulai->year)],
                [
                    'nama_proyek' => $nama,
                    'lokasi' => $lokasi,
                    'sumber_dana' => 'PAD Kota Bontang',
                    'tahun_anggaran' => $mulai->year,
                    'tanggal_spk' => $mulai->subDays(3)->toDateString(),
                    'tanggal_mulai' => $mulai->toDateString(),
                    'tanggal_selesai' => $selesai->toDateString(),
                    'jangka_waktu_hari' => $durasiMinggu * 7,
                    'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
                    'konsultan_pengawas' => 'CV. AKMAL BERKAH ABADI',
                    'nama_site_engineer' => 'ABDUL MUIZ, ST',
                    'nama_pelaksana_lapangan' => "A'ID MAGHFUR",
                    'qs_user_id' => $petugas->id,
                    'created_by' => $admin->id,
                    'status' => ProjectStatus::from($status)->value,
                ]
            );

            if ($project->workItems()->exists()) {
                continue; // Sudah pernah di-seed.
            }

            $project->assignments()->updateOrCreate(['user_id' => $petugas->id], ['peran' => 'QS', 'is_primary' => true]);
            $this->schedule->generateWeeklyPeriods($project);

            $kategori = $project->workCategories()->create(['kode' => 'A', 'nama' => 'PEKERJAAN UTAMA', 'urutan' => 1]);
            $periods = $project->periods()->orderBy('urutan')->get();

            $items = collect(self::PEKERJAAN)->map(fn ($p, $i) => $project->workItems()->create([
                'work_category_id' => $kategori->id,
                'unit_id' => $units[$p[1]],
                'uraian_pekerjaan' => $p[0],
                'volume' => $p[2],
                'harga_satuan' => $p[3],
                'urutan' => $i + 1,
                'period_mulai_id' => $periods->first()->id,
                'period_selesai_id' => $periods->last()->id,
            ]));

            $this->weights->recalculateProject($project);

            // Rencana: volume tiap pekerjaan dibagi rata ke seluruh periode.
            $rencana = [];
            foreach ($items as $item) {
                $bagian = $this->bagiVolume((float) $item->volume, $periods->count());
                foreach ($periods as $i => $period) {
                    $rencana[] = ['work_item_id' => $item->id, 'period_id' => $period->id, 'target_volume' => $bagian[$i]];
                }
            }
            $this->plans->sync($project, $rencana);

            // Realisasi: satu laporan per periode yang sudah lewat, sebesar rencana x faktor.
            foreach ($periods as $i => $period) {
                if ($faktor <= 0 || $period->tanggal_selesai->greaterThan($hariIni)) {
                    continue;
                }

                $this->progress->create($project, $petugas, [
                    'tanggal_laporan' => $period->tanggal_selesai->toDateString(),
                    'keterangan' => 'Realisasi pekerjaan '.$period->nama_periode.'.',
                    'lokasi' => $project->lokasi,
                    'cuaca' => 'Cerah berawan',
                    'status' => ReportStatus::DIKIRIM->value,
                    'details' => $items->map(fn ($item) => [
                        'work_item_id' => $item->id,
                        'volume_realisasi' => round($this->bagiVolume((float) $item->volume, $periods->count())[$i] * $faktor, 3),
                    ])->all(),
                ]);
            }
        }
    }

    /** @return array<int,float> */
    private function bagiVolume(float $total, int $bagian): array
    {
        $satuan = round($total / $bagian, 3);
        $hasil = array_fill(0, $bagian - 1, $satuan);
        $hasil[] = round($total - ($satuan * ($bagian - 1)), 3);

        return $hasil;
    }
}
