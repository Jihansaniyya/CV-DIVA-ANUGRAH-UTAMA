<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['m3', 'meter kubik'],
            ['m2', 'meter persegi'],
            ['m', 'meter'],
            ['kg', 'kilogram'],
            ['ton', 'ton'],
            ['ls', 'lump sum'],
            ['bh', 'buah'],
            ['unit', 'unit'],
            ['ttk', 'titik'],
        ];

        foreach ($units as [$code, $name]) {
            Unit::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
