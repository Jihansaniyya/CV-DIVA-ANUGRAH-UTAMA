<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Satuan ditulis huruf kecil (meter kubik, kilogram, m²), mengikuti penulisan satuan baku.
 * Kode hanya diubah bila versi huruf kecilnya belum dipakai satuan lain (kode harus unik).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('units')->get() as $unit) {
            $data = ['name' => mb_strtolower($unit->name)];
            $kode = mb_strtolower($unit->code);

            if ($kode !== $unit->code && ! DB::table('units')->where('code', $kode)->where('id', '!=', $unit->id)->exists()) {
                $data['code'] = $kode;
            }

            if ($data['name'] !== $unit->name || isset($data['code'])) {
                DB::table('units')->where('id', $unit->id)->update($data + ['updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Huruf kapital asli tidak disimpan; nama satuan tetap huruf kecil.
    }
};
