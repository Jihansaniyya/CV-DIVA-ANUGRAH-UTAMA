<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $mulai = now()->startOfWeek();

        return [
            'nama_proyek' => 'Pekerjaan '.fake()->unique()->words(3, true),
            'nomor_spk' => 'SPK/'.fake()->unique()->numerify('###')."/{$mulai->year}",
            'lokasi' => 'Kota Bontang',
            'sumber_dana' => 'PAD Kota Bontang',
            'tahun_anggaran' => $mulai->year,
            'tanggal_spk' => $mulai->copy()->subDays(3)->toDateString(),
            'tanggal_mulai' => $mulai->toDateString(),
            'tanggal_selesai' => $mulai->copy()->addWeeks(4)->subDay()->toDateString(),
            'jangka_waktu_hari' => 28,
            'kontraktor_pelaksana' => 'CV. DIVA ANUGRAH UTAMA',
            'konsultan_pengawas' => 'CV. AKMAL BERKAH ABADI',
            'nama_site_engineer' => 'Abdul Muiz, ST',
            'nama_pelaksana_lapangan' => "A'id Maghfur",
            'status' => ProjectStatus::BERJALAN->value,
        ];
    }
}
