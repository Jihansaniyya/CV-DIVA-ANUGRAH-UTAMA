<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['m3', 'Meter Kubik'],
            ['m2', 'Meter Persegi'],
            ['m1', 'Meter Lari'],
            ['kg', 'Kilogram'],
            ['ton', 'Ton'],
            ['ls', 'Lump Sum'],
            ['bh', 'Buah'],
            ['unit', 'Unit'],
            ['ttk', 'Titik'],
        ];

        foreach ($units as [$code, $name]) {
            Unit::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
