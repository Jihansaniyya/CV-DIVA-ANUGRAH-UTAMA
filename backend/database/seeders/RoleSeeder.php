<?php

namespace Database\Seeders;

use App\Enums\RoleCode;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [RoleCode::ADMIN, 'Admin', 'Mengelola pengguna, proyek, pekerjaan, rencana, dan target progres.'],
            [RoleCode::QS, 'Quantity Surveyor', 'Menginput progres aktual, dokumentasi, material, dan kendala pekerjaan.'],
            [RoleCode::KONTRAKTOR, 'Kontraktor', 'Memantau progres, Kurva S, deviasi, dan laporan proyek.'],
        ];

        foreach ($roles as [$code, $name, $description]) {
            Role::updateOrCreate(['code' => $code->value], ['name' => $name, 'description' => $description]);
        }
    }
}
