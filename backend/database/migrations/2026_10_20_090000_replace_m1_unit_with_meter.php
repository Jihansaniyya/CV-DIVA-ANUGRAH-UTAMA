<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Satuan "m1" (meter lari) diganti satuan "m" (meter); pekerjaan yang memakai m1 ikut dipindahkan. */
return new class extends Migration
{
    public function up(): void
    {
        $m1 = DB::table('units')->where('code', 'm1')->first();

        if (! $m1) {
            return;
        }

        $meter = DB::table('units')->where('code', 'm')->first();

        if ($meter) {
            DB::table('work_items')->where('unit_id', $m1->id)->update(['unit_id' => $meter->id]);
            DB::table('units')->where('id', $m1->id)->delete();

            return;
        }

        DB::table('units')->where('id', $m1->id)->update(['code' => 'm', 'name' => 'Meter', 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('units')->where('code', 'm')->update(['code' => 'm1', 'name' => 'Meter Lari', 'updated_at' => now()]);
    }
};
