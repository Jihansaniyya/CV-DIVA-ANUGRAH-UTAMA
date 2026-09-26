<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Unit;
use App\Models\User;
use App\Models\WorkCategory;
use App\Models\WorkItem;
use App\Services\ProgressService;
use App\Services\ProjectScheduleService;
use App\Services\WeightCalculatorService;
use App\Services\WorkPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Data contoh proyek CV Diva Anugrah Utama.
 *
 * Proyek pertama memakai data nyata dari laporan mingguan & bulanan referensi
 * (Pembuatan Penutup Parit RT 09 Kelurahan Bontang Baru) sehingga angka rencana,
 * realisasi, dan Kurva S yang dihasilkan aplikasi dapat dibandingkan langsung
 * dengan dokumen aslinya. Proyek lain memakai tanggal relatif terhadap hari ini
 * agar dashboard selalu menampilkan proyek berjalan dan proyek terlambat.
 */
class ProjectSeeder extends Seeder
{
    public function __construct(
        private readonly ProjectScheduleService $schedule,
        private readonly WeightCalculatorService $weights,
        private readonly WorkPlanService $plans,
        private readonly ProgressService $progress,
    ) {}

    public function run(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $nisa = User::where('username', 'qs.nisa')->firstOrFail();
        $jihan = User::where('username', 'qs.jihan')->firstOrFail();
        $units = Unit::pluck('id', 'code');

        $this->proyekPenutupParit($admin, $nisa, $units);
        $this->proyekJalanLingkungan($admin, $jihan, $units);
        $this->proyekDrainaseBelumMulai($admin, $nisa, $units);
    }

    /** Proyek selesai 100% sesuai laporan referensi. */
    private function proyekPenutupParit(User $admin, User $qs, $units): void
    {
        $mulai = CarbonImmutable::parse('2025-02-20');

        $project = Project::updateOrCreate(
            ['nomor_spk' => '000.3.2/98.1/SPK/Penutup Parit RT. 09-Kel.Boba/2025'],
            [
                'nama_proyek' => 'Belanja Modal Jalan Kota (Pembuatan Penutup Parit RT 09 Kelurahan Bontang Baru)',
                'lokasi' => 'RT. 09 Kel. Bontang Baru',
                'sumber_dana' => 'PAD Kota Bontang',
                'tahun_anggaran' => 2025,
                'tanggal_spk' => '2025-02-20',
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $mulai->addDays(41)->toDateString(),
                'jangka_waktu_hari' => 45,
                'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
                'konsultan_pengawas' => 'CV. AKMAL BERKAH ABADI',
                'nama_site_engineer' => 'ABDUL MUIZ, ST',
                'nama_pelaksana_lapangan' => "A'ID MAGHFUR",
                'qs_user_id' => $qs->id,
                'created_by' => $admin->id,
                'status' => ProjectStatus::SELESAI->value,
            ]
        );

        $project->assignments()->updateOrCreate(['user_id' => $qs->id], ['peran' => 'QS', 'is_primary' => true]);
        $this->schedule->generateWeeklyPeriods($project);

        $pendahuluan = $this->kategori($project, 'A', 'PEKERJAAN PENDAHULUAN', 1);
        $saluran = $this->kategori($project, 'B', 'PEKERJAAN SALURAN', 2);

        $daftar = [
            [$pendahuluan, 'Papan Nama Kegiatan', 'bh', 1.00, 500000.00, 1],
            [$pendahuluan, 'Biaya penyelenggaraan keselamatan dan kesehatan kerja konstruksi', 'ls', 1.00, 1470000.00, 2],
            [$saluran, 'Normalisasi Saluran', 'm3', 22.68, 179299.99, 3],
            [$saluran, 'Pek. Beton K-250 Ready Mix', 'm3', 23.87, 2909892.81, 4],
            [$saluran, 'Pek. Pembesian Polos', 'kg', 1874.37, 25096.50, 5],
            [$saluran, 'Pek. Bekisting', 'm2', 65.26, 301680.49, 6],
            [$saluran, 'Mainhole (Grill)', 'bh', 53.00, 564349.49, 7],
        ];

        $items = [];

        foreach ($daftar as [$kategori, $uraian, $satuan, $volume, $harga, $urutan]) {
            $items[$uraian] = $this->pekerjaan($project, $kategori, $units[$satuan], $uraian, $volume, $harga, $urutan);
        }

        $this->weights->recalculateProject($project);

        $periods = $project->periods()->orderBy('urutan')->get();

        // Rencana sesuai grid jangka waktu pada laporan bulanan referensi.
        $rows = [
            ['Papan Nama Kegiatan', 1, 1.00],
            ['Biaya penyelenggaraan keselamatan dan kesehatan kerja konstruksi', 1, 1.00],
            ['Normalisasi Saluran', 1, 22.68],
        ];

        $beton = $this->bagiVolume(23.87, 4);
        $besi = $this->bagiVolume(1874.37, 4);
        $bekisting = $this->bagiVolume(65.26, 4);

        foreach ([2, 3, 4, 5] as $index => $urutan) {
            $rows[] = ['Pek. Beton K-250 Ready Mix', $urutan, $beton[$index]];
            $rows[] = ['Pek. Pembesian Polos', $urutan, $besi[$index]];
            $rows[] = ['Pek. Bekisting', $urutan, $bekisting[$index]];
        }

        $rows[] = ['Mainhole (Grill)', 6, 53.00];

        $this->simpanRencana($project, collect($rows)->map(fn ($row) => [
            'work_item_id' => $items[$row[0]]->id,
            'period_id' => $periods->firstWhere('urutan', $row[1])->id,
            'target_volume' => $row[2],
        ])->all());

        // Realisasi: seluruh pekerjaan terlaksana sesuai rencana (deviasi 0).
        foreach ($periods as $period) {
            $rencana = $project->workPlans()->where('period_id', $period->id)->get();

            if ($rencana->isEmpty()) {
                continue;
            }

            $this->laporan($project, $qs, $period->tanggal_selesai->toDateString(), $rencana->map(fn ($plan) => [
                'work_item_id' => $plan->work_item_id,
                'volume_realisasi' => (float) $plan->target_volume,
            ])->all(), 'Pekerjaan '.$period->nama_periode.' terlaksana sesuai rencana.');
        }

        $project->milestones()->updateOrCreate(
            ['nama' => 'Penyelesaian pekerjaan beton saluran'],
            [
                'period_id' => $periods->firstWhere('urutan', 5)?->id,
                'deskripsi' => 'Seluruh pekerjaan beton, pembesian, dan bekisting selesai.',
                'tanggal_target' => $periods->firstWhere('urutan', 5)?->tanggal_selesai,
                'target_persentase' => 82.62,
                'status' => 'TERCAPAI',
            ]
        );

        $project->milestones()->updateOrCreate(
            ['nama' => 'Serah terima pekerjaan'],
            [
                'period_id' => $periods->last()?->id,
                'deskripsi' => 'Pemasangan mainhole selesai dan pekerjaan siap diserahterimakan.',
                'tanggal_target' => $periods->last()?->tanggal_selesai,
                'target_persentase' => 100,
                'status' => 'TERCAPAI',
            ]
        );
    }

    /** Proyek berjalan dengan realisasi di bawah rencana (deviasi negatif). */
    private function proyekJalanLingkungan(User $admin, User $qs, $units): void
    {
        $mulai = CarbonImmutable::now()->startOfWeek()->subWeeks(4);
        $selesai = $mulai->addWeeks(8)->subDay();

        $project = Project::updateOrCreate(
            ['nomor_spk' => '000.3.2/145/SPK/Jalan Lingkungan RT.12-Kel.Api-Api/'.$mulai->year],
            [
                'nama_proyek' => 'Belanja Modal Jalan Kota (Peningkatan Jalan Lingkungan RT 12 Kelurahan Api-Api)',
                'lokasi' => 'RT. 12 Kel. Api-Api, Bontang Utara',
                'sumber_dana' => 'PAD Kota Bontang',
                'tahun_anggaran' => $mulai->year,
                'tanggal_spk' => $mulai->subDays(3)->toDateString(),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jangka_waktu_hari' => 56,
                'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
                'konsultan_pengawas' => 'CV. AKMAL BERKAH ABADI',
                'nama_site_engineer' => 'ABDUL MUIZ, ST',
                'nama_pelaksana_lapangan' => "A'ID MAGHFUR",
                'qs_user_id' => $qs->id,
                'created_by' => $admin->id,
                'status' => ProjectStatus::BERJALAN->value,
            ]
        );

        $project->assignments()->updateOrCreate(['user_id' => $qs->id], ['peran' => 'QS', 'is_primary' => true]);
        $this->schedule->generateWeeklyPeriods($project);

        $pendahuluan = $this->kategori($project, 'A', 'PEKERJAAN PERSIAPAN', 1);
        $jalan = $this->kategori($project, 'B', 'PEKERJAAN JALAN', 2);

        $daftar = [
            [$pendahuluan, 'Papan Nama Kegiatan', 'bh', 1.00, 500000.00, 1],
            [$pendahuluan, 'Mobilisasi dan Demobilisasi Peralatan', 'ls', 1.00, 4250000.00, 2],
            [$jalan, 'Galian Tanah Biasa', 'm3', 148.50, 96500.00, 3],
            [$jalan, 'Urugan Sirtu Padat', 'm3', 96.25, 385000.00, 4],
            [$jalan, 'Lapis Pondasi Agregat Kelas B', 'm3', 74.80, 612500.00, 5],
            [$jalan, 'Pek. Rabat Beton K-225 Tebal 15 cm', 'm3', 112.40, 1985000.00, 6],
            [$jalan, 'Pek. Bekisting Rabat Beton', 'm2', 84.60, 298500.00, 7],
        ];

        $items = [];

        foreach ($daftar as [$kategori, $uraian, $satuan, $volume, $harga, $urutan]) {
            $items[$uraian] = $this->pekerjaan($project, $kategori, $units[$satuan], $uraian, $volume, $harga, $urutan);
        }

        $this->weights->recalculateProject($project);
        $periods = $project->periods()->orderBy('urutan')->get();

        $rencana = [
            ['Papan Nama Kegiatan', [1 => 1.00]],
            ['Mobilisasi dan Demobilisasi Peralatan', [1 => 1.00]],
            ['Galian Tanah Biasa', [1 => 74.25, 2 => 74.25]],
            ['Urugan Sirtu Padat', [2 => 48.125, 3 => 48.125]],
            ['Lapis Pondasi Agregat Kelas B', [3 => 37.40, 4 => 37.40]],
            ['Pek. Bekisting Rabat Beton', [4 => 28.20, 5 => 28.20, 6 => 28.20]],
            ['Pek. Rabat Beton K-225 Tebal 15 cm', [5 => 28.10, 6 => 28.10, 7 => 28.10, 8 => 28.10]],
        ];

        $rows = [];

        foreach ($rencana as [$uraian, $jadwal]) {
            foreach ($jadwal as $urutan => $volume) {
                $rows[] = [
                    'work_item_id' => $items[$uraian]->id,
                    'period_id' => $periods->firstWhere('urutan', $urutan)->id,
                    'target_volume' => $volume,
                ];
            }
        }

        $this->simpanRencana($project, $rows);

        // Realisasi 4 minggu pertama, sebagian di bawah target sehingga muncul deviasi negatif.
        $realisasi = [
            1 => [['Papan Nama Kegiatan', 1.00], ['Mobilisasi dan Demobilisasi Peralatan', 1.00], ['Galian Tanah Biasa', 74.25]],
            2 => [['Galian Tanah Biasa', 60.00], ['Urugan Sirtu Padat', 40.00]],
            3 => [['Urugan Sirtu Padat', 42.00], ['Lapis Pondasi Agregat Kelas B', 30.00]],
            4 => [['Lapis Pondasi Agregat Kelas B', 28.00], ['Pek. Bekisting Rabat Beton', 20.00]],
        ];

        foreach ($realisasi as $urutan => $baris) {
            $period = $periods->firstWhere('urutan', $urutan);

            $this->laporan($project, $qs, $period->tanggal_selesai->toDateString(), collect($baris)->map(fn ($row) => [
                'work_item_id' => $items[$row[0]]->id,
                'volume_realisasi' => $row[1],
            ])->all(), 'Realisasi pekerjaan '.$period->nama_periode.'.', $urutan >= 2);
        }

        $project->milestones()->updateOrCreate(
            ['nama' => 'Penyelesaian lapis pondasi agregat'],
            [
                'period_id' => $periods->firstWhere('urutan', 4)?->id,
                'deskripsi' => 'Lapis pondasi agregat kelas B siap untuk pekerjaan rabat beton.',
                'tanggal_target' => $periods->firstWhere('urutan', 4)?->tanggal_selesai,
                'target_persentase' => 45,
                'status' => 'BELUM_TERCAPAI',
            ]
        );
    }

    /** Proyek yang rencananya sudah disusun tetapi belum dimulai. */
    private function proyekDrainaseBelumMulai(User $admin, User $qs, $units): void
    {
        $mulai = CarbonImmutable::now()->startOfWeek()->addWeek();
        $selesai = $mulai->addWeeks(6)->subDay();

        $project = Project::updateOrCreate(
            ['nomor_spk' => '000.3.2/201/SPK/Drainase Kel.Gunung Elai/'.$mulai->year],
            [
                'nama_proyek' => 'Belanja Modal Drainase (Pembangunan Drainase Kelurahan Gunung Elai)',
                'lokasi' => 'Kel. Gunung Elai, Bontang Utara',
                'sumber_dana' => 'PAD Kota Bontang',
                'tahun_anggaran' => $mulai->year,
                'tanggal_spk' => $mulai->subDays(7)->toDateString(),
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jangka_waktu_hari' => 42,
                'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
                'konsultan_pengawas' => 'CV. AKMAL BERKAH ABADI',
                'nama_site_engineer' => 'ABDUL MUIZ, ST',
                'nama_pelaksana_lapangan' => "A'ID MAGHFUR",
                'qs_user_id' => $qs->id,
                'created_by' => $admin->id,
                'status' => ProjectStatus::BELUM_DIMULAI->value,
            ]
        );

        $project->assignments()->updateOrCreate(['user_id' => $qs->id], ['peran' => 'QS', 'is_primary' => true]);
        $this->schedule->generateWeeklyPeriods($project);

        $persiapan = $this->kategori($project, 'A', 'PEKERJAAN PERSIAPAN', 1);
        $drainase = $this->kategori($project, 'B', 'PEKERJAAN DRAINASE', 2);

        $daftar = [
            [$persiapan, 'Papan Nama Kegiatan', 'bh', 1.00, 500000.00, 1],
            [$drainase, 'Galian Saluran Drainase', 'm3', 132.60, 102500.00, 2],
            [$drainase, 'Pasangan Batu Camp. 1:4', 'm3', 88.40, 1285000.00, 3],
            [$drainase, 'Plesteran Camp. 1:3', 'm2', 176.80, 96500.00, 4],
            [$drainase, 'Pek. Tutup Saluran Beton Pracetak', 'bh', 64.00, 485000.00, 5],
        ];

        $items = [];

        foreach ($daftar as [$kategori, $uraian, $satuan, $volume, $harga, $urutan]) {
            $items[$uraian] = $this->pekerjaan($project, $kategori, $units[$satuan], $uraian, $volume, $harga, $urutan);
        }

        $this->weights->recalculateProject($project);
        $periods = $project->periods()->orderBy('urutan')->get();

        $rencana = [
            ['Papan Nama Kegiatan', [1 => 1.00]],
            ['Galian Saluran Drainase', [1 => 66.30, 2 => 66.30]],
            ['Pasangan Batu Camp. 1:4', [2 => 29.47, 3 => 29.47, 4 => 29.46]],
            ['Plesteran Camp. 1:3', [4 => 88.40, 5 => 88.40]],
            ['Pek. Tutup Saluran Beton Pracetak', [5 => 32.00, 6 => 32.00]],
        ];

        $rows = [];

        foreach ($rencana as [$uraian, $jadwal]) {
            foreach ($jadwal as $urutan => $volume) {
                $rows[] = [
                    'work_item_id' => $items[$uraian]->id,
                    'period_id' => $periods->firstWhere('urutan', $urutan)->id,
                    'target_volume' => $volume,
                ];
            }
        }

        $this->simpanRencana($project, $rows);
    }

    /**
     * Tetapkan Periode Mulai/Selesai tiap pekerjaan dari periode rencananya,
     * lalu simpan rencana. Pekerjaan tanpa rencana memakai seluruh periode proyek.
     *
     * @param  array<int,array{work_item_id:int,period_id:int,target_volume:float}>  $rows
     */
    private function simpanRencana(Project $project, array $rows): void
    {
        $periods = $project->periods()->orderBy('urutan')->get()->keyBy('id');
        $perPekerjaan = collect($rows)->groupBy('work_item_id');

        foreach ($project->workItems()->get() as $item) {
            $urutan = $perPekerjaan->get($item->id, collect())->map(fn ($row) => $periods[$row['period_id']]->urutan);
            $mulai = $urutan->isEmpty() ? $periods->first() : $periods->firstWhere('urutan', $urutan->min());
            $selesai = $urutan->isEmpty() ? $periods->last() : $periods->firstWhere('urutan', $urutan->max());

            $item->update(['period_mulai_id' => $mulai->id, 'period_selesai_id' => $selesai->id]);
        }

        $this->plans->sync($project, $rows);
    }

    /**
     * Bagi volume pekerjaan ke beberapa periode tanpa selisih pembulatan:
     * sisa pembulatan dimasukkan ke bagian terakhir.
     *
     * @return array<int,float>
     */
    private function bagiVolume(float $total, int $bagian): array
    {
        $satuan = round($total / $bagian, 3);
        $hasil = array_fill(0, $bagian - 1, $satuan);
        $hasil[] = round($total - ($satuan * ($bagian - 1)), 3);

        return $hasil;
    }

    private function kategori(Project $project, string $kode, string $nama, int $urutan): WorkCategory
    {
        return $project->workCategories()->updateOrCreate(['kode' => $kode], ['nama' => $nama, 'urutan' => $urutan]);
    }

    private function pekerjaan(
        Project $project,
        WorkCategory $kategori,
        int $unitId,
        string $uraian,
        float $volume,
        float $hargaSatuan,
        int $urutan,
    ): WorkItem {
        return $project->workItems()->updateOrCreate(
            ['uraian_pekerjaan' => $uraian],
            [
                'work_category_id' => $kategori->id,
                'unit_id' => $unitId,
                'volume' => $volume,
                'harga_satuan' => $hargaSatuan,
                'urutan' => $urutan,
            ]
        );
    }

    /** @param array<int,array{work_item_id:int,volume_realisasi:float}> $details */
    private function laporan(Project $project, User $qs, string $tanggal, array $details, string $keterangan, bool $denganKendala = false): void
    {
        if ($project->progressReports()->whereDate('tanggal_laporan', $tanggal)->exists()) {
            return;
        }

        $data = [
            'tanggal_laporan' => $tanggal,
            'keterangan' => $keterangan,
            'lokasi' => $project->lokasi,
            'cuaca' => 'Cerah berawan',
            'status' => ReportStatus::DIKIRIM->value,
            'details' => $details,
        ];

        if ($denganKendala) {
            $data['issues'] = [[
                'jenis_kendala' => 'CUACA',
                'deskripsi' => 'Hujan pada sore hari sehingga pengecoran dihentikan lebih awal.',
                'alasan_keterlambatan' => 'Curah hujan tinggi selama 2 hari kerja.',
                'tindak_lanjut' => 'Penambahan jam kerja pada minggu berikutnya.',
                'status' => 'DALAM_PENANGANAN',
            ]];
        }

        $this->progress->create($project, $qs, $data);
    }
}
