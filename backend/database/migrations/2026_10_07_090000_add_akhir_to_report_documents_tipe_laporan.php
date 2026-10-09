<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Riwayat export Laporan Akhir dicatat dengan tipe AKHIR. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_documents', function (Blueprint $table) {
            $table->enum('tipe_laporan', ['HARIAN', 'MINGGUAN', 'BULANAN', 'MILESTONE', 'AKHIR'])->change();
        });
    }

    public function down(): void
    {
        DB::table('report_documents')->where('tipe_laporan', 'AKHIR')->delete();

        Schema::table('report_documents', function (Blueprint $table) {
            $table->enum('tipe_laporan', ['HARIAN', 'MINGGUAN', 'BULANAN', 'MILESTONE'])->change();
        });
    }
};
