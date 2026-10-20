<?php

namespace Database\Seeders;

use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\ProjectScheduleService;
use App\Services\ProjectStatusService;
use App\Services\WeightCalculatorService;
use App\Services\WorkPlanService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Proyek dummy yang sudah selesai: rencana 100% dan realisasi 100%.
 *
 * Setiap minggu dilaporkan tepat sesuai rencana, lengkap dengan lokasi, keterangan, dan satu
 * foto bukti (syarat laporan terkirim), sehingga status proyek menjadi Selesai dengan deviasi 0.
 *
 * Tidak dipanggil oleh DatabaseSeeder. Jalankan manual (menjalankan ulang membangun ulang data):
 *   php artisan db:seed --class=DummyProyekSelesaiSeeder
 *
 * Nomor SPK berawalan "DUMMY-SELESAI/" sehingga mudah dihapus:
 *   Project::withTrashed()->where('nomor_spk', 'like', 'DUMMY-SELESAI/%')->forceDelete();
 */
class DummyProyekSelesaiSeeder extends Seeder
{
    private const AWALAN_SPK = 'DUMMY-SELESAI/';

    /** [nama, lokasi, jenis pekerjaan, selesai (minggu sebelum hari ini), durasi (minggu), sumber dana, konsultan] */
    private const PROYEK = [
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 05 Kelurahan Belimbing)', 'RT. 05 Kel. Belimbing, Bontang Barat', 'jalan', 2, 8, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Drainase (Pembangunan Saluran RT 20 Kelurahan Gunung Telihan)', 'RT. 20 Kel. Gunung Telihan, Bontang Barat', 'drainase', 4, 6, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Gedung (Rehabilitasi Posyandu Kelurahan Lok Tuan)', 'Kel. Lok Tuan, Bontang Utara', 'gedung', 6, 8, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Gang Melati RT 13 Kelurahan Tanjung Laut)', 'RT. 13 Kel. Tanjung Laut, Bontang Selatan', 'paving', 3, 4, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Turap (Pembangunan Talud Penahan Tanah RT 09 Kelurahan Guntung)', 'RT. 09 Kel. Guntung, Bontang Utara', 'turap', 8, 9, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Jalan Kota (Semenisasi Gang Kenanga RT 14 Kelurahan Api-Api)', 'RT. 14 Kel. Api-Api, Bontang Utara', 'jalan', 1, 6, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Drainase (Normalisasi Saluran RT 03 Kelurahan Bontang Baru)', 'RT. 03 Kel. Bontang Baru, Bontang Utara', 'drainase', 2, 5, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Gedung (Pembangunan Pos Ronda RT 17 Kelurahan Kanaan)', 'RT. 17 Kel. Kanaan, Bontang Barat', 'gedung', 3, 6, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jembatan (Rehabilitasi Jembatan Penghubung RT 08 Kelurahan Bontang Kuala)', 'RT. 08 Kel. Bontang Kuala, Bontang Utara', 'jembatan', 4, 10, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Halaman Kantor Kelurahan Satimpo)', 'Kel. Satimpo, Bontang Selatan', 'paving', 5, 4, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Turap (Pembangunan Turap Sungai RT 22 Kelurahan Gunung Elai)', 'RT. 22 Kel. Gunung Elai, Bontang Utara', 'turap', 6, 10, 'DAK Fisik', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Usaha Tani Kelurahan Bontang Lestari)', 'Kel. Bontang Lestari, Bontang Selatan', 'jalan', 7, 12, 'DAK Fisik', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Drainase (Pembuatan Penutup Parit RT 11 Kelurahan Berbas Tengah)', 'RT. 11 Kel. Berbas Tengah, Bontang Selatan', 'drainase', 8, 6, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Gedung (Rehabilitasi Balai Warga RT 06 Kelurahan Telihan)', 'RT. 06 Kel. Telihan, Bontang Barat', 'gedung', 9, 8, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Taman RT 25 Kelurahan Berbas Pantai)', 'RT. 25 Kel. Berbas Pantai, Bontang Selatan', 'paving', 10, 5, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jembatan (Pembangunan Jembatan Ulin RT 02 Kelurahan Tanjung Laut Indah)', 'RT. 02 Kel. Tanjung Laut Indah, Bontang Selatan', 'jembatan', 11, 9, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Drainase (Pembangunan Saluran Jl. Bhayangkara Kelurahan Belimbing)', 'Jl. Bhayangkara, Kel. Belimbing, Bontang Barat', 'drainase', 12, 7, 'DAK Fisik', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 19 Kelurahan Lok Tuan)', 'RT. 19 Kel. Lok Tuan, Bontang Utara', 'jalan', 13, 8, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Turap (Pembangunan Talud Jalan RT 04 Kelurahan Gunung Telihan)', 'RT. 04 Kel. Gunung Telihan, Bontang Barat', 'turap', 14, 8, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Gedung (Pembangunan Gedung Serbaguna Kelurahan Guntung)', 'Kel. Guntung, Bontang Utara', 'gedung', 15, 12, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Gang Anggrek RT 07 Kelurahan Kanaan)', 'RT. 07 Kel. Kanaan, Bontang Barat', 'paving', 16, 4, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Drainase (Normalisasi Saluran Sekunder Kelurahan Tanjung Laut)', 'Kel. Tanjung Laut, Bontang Selatan', 'drainase', 17, 8, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jembatan (Pembangunan Jembatan Beton RT 15 Kelurahan Berbas Pantai)', 'RT. 15 Kel. Berbas Pantai, Bontang Selatan', 'jembatan', 18, 11, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Jalan Kota (Semenisasi Jalan Lingkungan RT 10 Kelurahan Gunung Elai)', 'RT. 10 Kel. Gunung Elai, Bontang Utara', 'jalan', 19, 6, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Gedung (Rehabilitasi Kantor Kelurahan Bontang Baru)', 'Kel. Bontang Baru, Bontang Utara', 'gedung', 20, 10, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Turap (Pembangunan Turap Pantai Kelurahan Bontang Kuala)', 'Kel. Bontang Kuala, Bontang Utara', 'turap', 21, 12, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Drainase (Pembangunan Saluran RT 28 Kelurahan Api-Api)', 'RT. 28 Kel. Api-Api, Bontang Utara', 'drainase', 22, 6, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Halaman Sekolah Kelurahan Satimpo)', 'Kel. Satimpo, Bontang Selatan', 'paving', 23, 5, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 01 Kelurahan Telihan)', 'RT. 01 Kel. Telihan, Bontang Barat', 'jalan', 24, 9, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jembatan (Rehabilitasi Jembatan Kayu RT 12 Kelurahan Tanjung Laut)', 'RT. 12 Kel. Tanjung Laut, Bontang Selatan', 'jembatan', 25, 8, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
    ];

    public function __construct(
        private readonly ProjectScheduleService $schedule,
        private readonly WeightCalculatorService $weights,
        private readonly WorkPlanService $plans,
        private readonly ProgressService $progress,
        private readonly ProjectStatusService $status,
    ) {}

    public function run(): void
    {
        $admin = User::where('email', 'admin@divaanugrahutama.co.id')->firstOrFail();
        $qs = User::whereIn('email', ['nisa@divaanugrahutama.co.id', 'jihan@divaanugrahutama.co.id'])->get()->values();
        $units = Unit::pluck('id', 'code');
        $hariIni = ProgressService::hariIni()->startOfDay();

        // forceDelete karena Project memakai soft delete; relasi ikut terhapus via cascade.
        Project::withTrashed()->where('nomor_spk', 'like', self::AWALAN_SPK.'%')->forceDelete();

        foreach (self::PROYEK as $index => [$nama, $lokasi, $jenis, $selesaiMinggu, $durasiMinggu, $sumberDana, $konsultan]) {
            $selesai = $hariIni->startOfWeek()->subWeeks($selesaiMinggu)->subDay();
            $mulai = $selesai->subWeeks($durasiMinggu)->addDay();
            $petugas = $qs[$index % $qs->count()];

            $project = Project::create([
                'nomor_spk' => sprintf('%s%03d/SPK/%d', self::AWALAN_SPK, $index + 1, $mulai->year),
                'nama_proyek' => $nama,
                'lokasi' => $lokasi,
                'sumber_dana' => $sumberDana,
                'tahun_anggaran' => $mulai->year,
                'tanggal_spk' => $mulai->subDays(5)->toDateString(),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jangka_waktu_hari' => $durasiMinggu * 7,
                'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
                'konsultan_pengawas' => $konsultan,
                'nama_site_engineer' => 'ABDUL MUIZ, ST',
                'nama_pelaksana_lapangan' => "A'ID MAGHFUR",
                'qs_user_id' => $petugas->id,
                'created_by' => $admin->id,
            ]);

            $project->assignments()->create(['user_id' => $petugas->id, 'peran' => 'QS', 'is_primary' => true]);
            $this->schedule->generateWeeklyPeriods($project);
            $periods = $project->periods()->orderBy('urutan')->get()->values();

            $rencana = $this->susunPekerjaanDanRencana($project, $jenis, $periods, $units);

            // Realisasi: setiap minggu dilaporkan tepat sebesar rencananya, pada hari terakhir minggu itu.
            foreach ($periods as $p => $period) {
                if (empty($rencana[$p])) {
                    continue;
                }

                $this->progress->create($project, $petugas, [
                    'tanggal_laporan' => $period->tanggal_selesai->toDateString(),
                    'keterangan' => 'Pekerjaan '.$period->nama_periode.' terlaksana sesuai rencana.',
                    'lokasi' => $project->lokasi,
                    'cuaca' => 'Cerah berawan',
                    'status' => ReportStatus::DIKIRIM->value,
                    'details' => collect($rencana[$p])->map(fn ($row) => [
                        'work_item_id' => $row['work_item_id'],
                        'volume_realisasi' => $row['target_volume'],
                    ])->all(),
                    'photo_captions' => ['Dokumentasi '.$period->nama_periode],
                ], [self::fotoBukti($period->nama_periode)]);
            }

            $this->status->sinkronkan($project);
        }
    }

    /**
     * Buat kategori, pekerjaan, dan rencana 100% bertahap: pekerjaan awal di minggu pertama,
     * pekerjaan berikutnya bergeser ke kanan.
     *
     * @return array<int,array<int,array{work_item_id:int,period_id:int,target_volume:float}>> rencana per indeks periode
     */
    private function susunPekerjaanDanRencana(Project $project, string $jenis, $periods, $units): array
    {
        $daftar = [];
        $urutanKategori = 0;

        foreach (DummyProyekSeeder::PEKERJAAN[$jenis] as $namaKategori => $pekerjaan) {
            $urutanKategori++;
            $kategori = $project->workCategories()->create([
                'kode' => chr(64 + $urutanKategori),
                'nama' => $namaKategori,
                'urutan' => $urutanKategori,
            ]);

            foreach ($pekerjaan as $p) {
                $daftar[] = [$kategori, $p];
            }
        }

        $jumlahPeriode = $periods->count();
        $jumlahItem = count($daftar);
        $rencana = [];

        foreach ($daftar as $i => [$kategori, [$uraian, $satuan, $volume, $harga]]) {
            $awal = $i === 0 ? 0 : (int) floor($i * $jumlahPeriode / ($jumlahItem + 1));
            $panjang = $i === 0 ? 1 : max(1, (int) ceil($jumlahPeriode / 3));
            $akhir = min($jumlahPeriode - 1, $awal + $panjang - 1);

            $item = $project->workItems()->create([
                'work_category_id' => $kategori->id,
                'unit_id' => $units[$satuan],
                'uraian_pekerjaan' => $uraian,
                'volume' => $volume,
                'harga_satuan' => $harga,
                'urutan' => $i + 1,
                'period_mulai_id' => $periods[$awal]->id,
                'period_selesai_id' => $periods[$akhir]->id,
            ]);

            $bagian = $this->bagiVolume($volume, $akhir - $awal + 1);

            foreach (range($awal, $akhir) as $j => $p) {
                $rencana[$p][] = ['work_item_id' => $item->id, 'period_id' => $periods[$p]->id, 'target_volume' => $bagian[$j]];
            }
        }

        $this->weights->recalculateProject($project);
        $this->plans->sync($project, collect($rencana)->flatten(1)->all());

        return $rencana;
    }

    /** Foto contoh sederhana (JPEG) bertuliskan periode, sebagai pengganti foto lapangan. */
    public static function fotoBukti(string $periode): UploadedFile
    {
        $gambar = imagecreatetruecolor(800, 600);
        imagefill($gambar, 0, 0, imagecolorallocate($gambar, 120, 132, 148));
        imagestring($gambar, 5, 300, 290, 'Dokumentasi '.$periode, imagecolorallocate($gambar, 255, 255, 255));

        $path = tempnam(sys_get_temp_dir(), 'foto').'.jpg';
        imagejpeg($gambar, $path, 80);
        imagedestroy($gambar);

        return new UploadedFile($path, 'dokumentasi-'.strtolower($periode).'.jpg', 'image/jpeg', null, true);
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
