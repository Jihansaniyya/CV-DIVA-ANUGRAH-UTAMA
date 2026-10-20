<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProgressService;
use App\Services\ProjectScheduleService;
use App\Services\ProjectStatusService;
use App\Services\WeightCalculatorService;
use App\Services\WorkPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 15 proyek dummy dengan item pekerjaan yang berbeda sesuai jenis proyek
 * (jalan, drainase, gedung, paving, jembatan, turap).
 *
 * Tidak dipanggil oleh DatabaseSeeder. Jalankan manual (menjalankan ulang membangun ulang
 * seluruh proyek DUMMY agar jadwalnya kembali sesuai tanggal hari ini):
 *   php artisan db:seed --class=DummyProyekSeeder
 *
 * Semua proyek memakai nomor SPK berawalan "DUMMY/" sehingga mudah dihapus:
 *   Project::withTrashed()->where('nomor_spk', 'like', 'DUMMY/%')->forceDelete();
 */
class DummyProyekSeeder extends Seeder
{
    /**
     * [nama, lokasi, jenis, status, mulai (minggu dari sekarang), durasi (minggu), faktor realisasi, sumber dana, konsultan]
     * Faktor realisasi < 1 membuat proyek tertinggal dari rencana.
     */
    private const PROYEK = [
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 07 Kelurahan Bontang Baru)', 'RT. 07 Kel. Bontang Baru, Bontang Utara', 'jalan', 'BERJALAN', -4, 8, 0.92, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Drainase (Pembangunan Saluran RT 15 Kelurahan Lok Tuan)', 'RT. 15 Kel. Lok Tuan, Bontang Utara', 'drainase', 'BERJALAN', -3, 6, 1.00, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Gedung (Rehabilitasi Balai Pertemuan Kelurahan Gunung Elai)', 'Kel. Gunung Elai, Bontang Utara', 'gedung', 'BERJALAN', -5, 10, 0.80, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Gang Mawar RT 22 Kelurahan Api-Api)', 'RT. 22 Kel. Api-Api, Bontang Utara', 'paving', 'BERJALAN', -2, 5, 0.95, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jembatan (Pembangunan Jembatan Beton RT 04 Kelurahan Tanjung Laut)', 'RT. 04 Kel. Tanjung Laut, Bontang Selatan', 'jembatan', 'BERJALAN', -6, 12, 0.75, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Turap (Pembangunan Turap Sungai Kelurahan Guntung)', 'Kel. Guntung, Bontang Utara', 'turap', 'BERJALAN', -5, 10, 0.88, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Drainase (Normalisasi Saluran Jl. MT Haryono Kelurahan Belimbing)', 'Jl. MT Haryono, Kel. Belimbing, Bontang Barat', 'drainase', 'BERJALAN', -1, 6, 1.00, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Jalan Kota (Semenisasi Jalan Usaha Tani Kelurahan Bontang Lestari)', 'Kel. Bontang Lestari, Bontang Selatan', 'jalan', 'BERJALAN', -6, 8, 0.85, 'DAK Fisik', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Gedung (Pembangunan Pos Kamling RT 10 Kelurahan Satimpo)', 'RT. 10 Kel. Satimpo, Bontang Selatan', 'gedung', 'TERLAMBAT', -9, 6, 0.82, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Drainase (Pembuatan Penutup Parit RT 18 Kelurahan Berbas Tengah)', 'RT. 18 Kel. Berbas Tengah, Bontang Selatan', 'drainase', 'TERLAMBAT', -10, 7, 0.78, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 02 Kelurahan Kanaan)', 'RT. 02 Kel. Kanaan, Bontang Barat', 'jalan', 'SELESAI', -18, 8, 1.00, 'PAD Kota Bontang', 'CV. AKMAL BERKAH ABADI'],
        ['Belanja Modal Jalan Kota (Pemasangan Paving Block Halaman Masjid Kelurahan Berbas Pantai)', 'Kel. Berbas Pantai, Bontang Selatan', 'paving', 'SELESAI', -14, 4, 1.00, 'PAD Kota Bontang', 'CV. CIPTA KARYA KONSULTAN'],
        ['Belanja Modal Turap (Pembangunan Talud Penahan Tanah RT 26 Kelurahan Telihan)', 'RT. 26 Kel. Telihan, Bontang Barat', 'turap', 'SELESAI', -22, 9, 1.00, 'DAK Fisik', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Jembatan (Rehabilitasi Jembatan Ulin RT 11 Kelurahan Bontang Kuala)', 'RT. 11 Kel. Bontang Kuala, Bontang Utara', 'jembatan', 'BELUM_DIMULAI', 1, 10, 0, 'Bantuan Keuangan Provinsi Kaltim', 'PT. BINA TEKNIK MANDIRI'],
        ['Belanja Modal Gedung (Pembangunan Gedung Posyandu Kelurahan Tanjung Laut Indah)', 'Kel. Tanjung Laut Indah, Bontang Selatan', 'gedung', 'BELUM_DIMULAI', 2, 8, 0, 'DAK Fisik', 'CV. CIPTA KARYA KONSULTAN'],
    ];

    /**
     * Proyek yang rencananya belum lengkap (indeks PROYEK => porsi pekerjaan yang sudah direncanakan).
     * Sesuai aturan aplikasi, progres baru dapat diinput setelah bobot rencana 100%, jadi proyek
     * ini tidak memiliki laporan progres. Proyek lain direncanakan penuh (100%).
     */
    private const RENCANA_BELUM_LENGKAP = [
        6 => 0.5,  // DUMMY/007: sudah mulai, Admin baru menjadwalkan separuh pekerjaan.
        13 => 0.5, // DUMMY/014: belum mulai, rencana baru separuh.
        14 => 0,   // DUMMY/015: belum mulai, rencana belum disusun sama sekali.
    ];

    /** Item pekerjaan per jenis proyek: kategori => [[uraian, satuan, volume, harga satuan], ...] */
    private const PEKERJAAN = [
        'jalan' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
                ['Mobilisasi dan Demobilisasi Peralatan', 'ls', 1.00, 4250000.00],
                ['Biaya penyelenggaraan keselamatan dan kesehatan kerja konstruksi', 'ls', 1.00, 1470000.00],
            ],
            'PEKERJAAN JALAN' => [
                ['Galian Tanah Biasa', 'm3', 165.00, 96500.00],
                ['Urugan Sirtu Padat', 'm3', 110.00, 385000.00],
                ['Lapis Pondasi Agregat Kelas B', 'm3', 82.50, 612500.00],
                ['Pek. Rabat Beton K-225 Tebal 15 cm', 'm3', 123.75, 1985000.00],
                ['Pek. Bekisting Rabat Beton', 'm2', 99.00, 298500.00],
            ],
        ],
        'drainase' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
                ['Biaya penyelenggaraan keselamatan dan kesehatan kerja konstruksi', 'ls', 1.00, 1470000.00],
            ],
            'PEKERJAAN SALURAN' => [
                ['Galian Saluran Drainase', 'm3', 142.80, 102500.00],
                ['Pasangan Batu Camp. 1:4', 'm3', 95.20, 1285000.00],
                ['Plesteran Camp. 1:3', 'm2', 190.40, 96500.00],
                ['Pek. Tutup Saluran Beton Pracetak', 'bh', 68.00, 485000.00],
            ],
        ],
        'gedung' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
                ['Pembersihan Lokasi dan Bouwplank', 'ls', 1.00, 2150000.00],
            ],
            'PEKERJAAN STRUKTUR' => [
                ['Galian Tanah Pondasi', 'm3', 28.50, 96500.00],
                ['Pasangan Pondasi Batu Kali Camp. 1:4', 'm3', 18.40, 1285000.00],
                ['Pek. Beton Sloof, Kolom, dan Balok K-225', 'm3', 9.60, 4650000.00],
            ],
            'PEKERJAAN ARSITEKTUR' => [
                ['Pasangan Dinding Bata Ringan', 'm2', 142.00, 168500.00],
                ['Plesteran dan Acian Dinding', 'm2', 284.00, 92500.00],
                ['Pemasangan Rangka Atap Baja Ringan', 'm2', 96.00, 285000.00],
                ['Pemasangan Keramik Lantai 40x40', 'm2', 72.00, 215000.00],
                ['Pengecatan Dinding', 'm2', 284.00, 42500.00],
            ],
        ],
        'paving' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
            ],
            'PEKERJAAN PAVING' => [
                ['Perataan dan Pemadatan Tanah Dasar', 'm2', 420.00, 18500.00],
                ['Hamparan Pasir Urug Tebal 5 cm', 'm3', 21.00, 285000.00],
                ['Pemasangan Paving Block K-300 Tebal 6 cm', 'm2', 420.00, 168000.00],
                ['Pemasangan Kanstin Beton', 'm', 168.00, 112500.00],
            ],
        ],
        'jembatan' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
                ['Mobilisasi dan Demobilisasi Peralatan', 'ls', 1.00, 6750000.00],
            ],
            'PEKERJAAN BANGUNAN BAWAH' => [
                ['Pemancangan Tiang Ulin 10/10', 'm', 240.00, 165000.00],
                ['Pek. Beton Abutment K-250', 'm3', 18.60, 2909892.81],
                ['Pek. Pembesian Ulir', 'kg', 2240.00, 25096.50],
            ],
            'PEKERJAAN BANGUNAN ATAS' => [
                ['Pek. Beton Lantai Jembatan K-250', 'm3', 14.40, 2909892.81],
                ['Pek. Bekisting Lantai Jembatan', 'm2', 64.00, 301680.49],
                ['Pemasangan Railing Pipa Galvanis', 'm', 48.00, 425000.00],
            ],
        ],
        'turap' => [
            'PEKERJAAN PERSIAPAN' => [
                ['Papan Nama Kegiatan', 'bh', 1.00, 500000.00],
                ['Biaya penyelenggaraan keselamatan dan kesehatan kerja konstruksi', 'ls', 1.00, 1470000.00],
            ],
            'PEKERJAAN TURAP' => [
                ['Galian Tanah Pondasi Turap', 'm3', 186.00, 102500.00],
                ['Pemancangan Cerucuk Galam', 'm', 1450.00, 18500.00],
                ['Pasangan Batu Camp. 1:4', 'm3', 212.00, 1285000.00],
                ['Plesteran Camp. 1:3', 'm2', 248.00, 96500.00],
                ['Pemasangan Pipa Suling-suling PVC 2"', 'm', 64.00, 48500.00],
            ],
        ],
    ];

    /** Kendala yang dipilih bergiliran untuk laporan proyek yang tertinggal. */
    private const KENDALA = [
        ['CUACA', 'Hujan deras pada siang hari sehingga pekerjaan dihentikan lebih awal.', 'Penambahan jam kerja pada hari berikutnya.'],
        ['MATERIAL', 'Pengiriman material terlambat 2 hari dari pemasok.', 'Koordinasi ulang jadwal pengiriman dengan pemasok.'],
        ['TENAGA_KERJA', 'Jumlah tukang berkurang karena sebagian pekerja izin.', 'Penambahan tenaga kerja harian.'],
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
        $hariIni = CarbonImmutable::now()->startOfDay();

        // Jadwal dihitung relatif terhadap hari ini, jadi data lama dibangun ulang agar tetap sesuai tanggal.
        // forceDelete karena Project memakai soft delete; relasi ikut terhapus via cascade.
        Project::withTrashed()->where('nomor_spk', 'like', 'DUMMY/%')->forceDelete();

        foreach (self::PROYEK as $index => [$nama, $lokasi, $jenis, $status, $mulaiMinggu, $durasiMinggu, $faktor, $sumberDana, $konsultan]) {
            // Geser hari mulai agar tanggal laporan antarproyek tidak sama persis.
            $mulai = $hariIni->startOfWeek()->addWeeks($mulaiMinggu)->addDays($index % 5);
            $selesai = $mulai->addWeeks($durasiMinggu)->subDay();
            $petugas = $qs[$index % $qs->count()];

            $project = Project::updateOrCreate(
                ['nomor_spk' => sprintf('DUMMY/%03d/SPK/%d', $index + 1, $mulai->year)],
                [
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
                    'status' => ProjectStatus::from($status)->value,
                ]
            );

            $project->assignments()->updateOrCreate(['user_id' => $petugas->id], ['peran' => 'QS', 'is_primary' => true]);
            $this->schedule->generateWeeklyPeriods($project);
            $periods = $project->periods()->orderBy('urutan')->get()->values();
            $jumlahPeriode = $periods->count();

            $daftar = [];
            $urutanKategori = 0;

            foreach (self::PEKERJAAN[$jenis] as $namaKategori => $pekerjaan) {
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

            // Jadwal bertahap: pekerjaan awal di minggu pertama, pekerjaan berikutnya bergeser ke kanan.
            $rencana = [];
            $items = collect();
            $jumlahItem = count($daftar);
            $rencanaLengkap = ! array_key_exists($index, self::RENCANA_BELUM_LENGKAP);
            $jumlahDirencanakan = $rencanaLengkap ? $jumlahItem : (int) floor($jumlahItem * self::RENCANA_BELUM_LENGKAP[$index]);

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
                $items->push($item);

                if ($i >= $jumlahDirencanakan) {
                    continue; // Pekerjaan ini belum dijadwalkan Admin.
                }

                $bagian = $this->bagiVolume($volume, $akhir - $awal + 1);

                foreach (range($awal, $akhir) as $j => $p) {
                    $rencana[$p][] = ['work_item_id' => $item->id, 'period_id' => $periods[$p]->id, 'target_volume' => $bagian[$j]];
                }
            }

            $this->weights->recalculateProject($project);
            if ($rencana !== []) {
                $this->plans->sync($project, collect($rencana)->flatten(1)->all());
            }

            if (! $rencanaLengkap) {
                $this->status->sinkronkan($project);

                continue; // Rencana belum 100%: progres belum dapat diinput.
            }

            // Realisasi: satu laporan per periode yang sudah berjalan, sebesar rencana x faktor.
            // Periode yang sedang berjalan ikut dilaporkan (tanggal hari ini) karena Progres Rencana
            // s/d hari ini sudah menghitung periode tersebut; tanpa laporan ini deviasi tampak tertinggal.
            foreach ($periods as $p => $period) {
                if ($faktor <= 0 || $period->tanggal_mulai->greaterThan($hariIni) || empty($rencana[$p])) {
                    continue;
                }

                $tanggalLaporan = $period->tanggal_selesai->greaterThan($hariIni) ? $hariIni : $period->tanggal_selesai;

                $data = [
                    'tanggal_laporan' => $tanggalLaporan->toDateString(),
                    'keterangan' => 'Realisasi pekerjaan '.$period->nama_periode.'.',
                    'lokasi' => $project->lokasi,
                    'cuaca' => $p % 3 === 1 ? 'Hujan ringan' : 'Cerah berawan',
                    'status' => ReportStatus::DIKIRIM->value,
                    'details' => collect($rencana[$p])->map(fn ($row) => [
                        'work_item_id' => $row['work_item_id'],
                        'volume_realisasi' => round($row['target_volume'] * $faktor, 3),
                    ])->all(),
                ];

                if ($faktor < 1 && $p % 2 === 1) {
                    [$jenisKendala, $deskripsi, $tindakLanjut] = self::KENDALA[($index + $p) % count(self::KENDALA)];
                    $data['issues'] = [[
                        'jenis_kendala' => $jenisKendala,
                        'deskripsi' => $deskripsi,
                        'tindak_lanjut' => $tindakLanjut,
                        'status' => 'DALAM_PENANGANAN',
                    ]];
                }

                $this->progress->create($project, $petugas, $data);
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
