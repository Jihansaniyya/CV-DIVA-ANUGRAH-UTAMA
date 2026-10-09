<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan harian dan laporan milestone tidak lagi tersedia; jenis laporan hanya Mingguan, Bulanan, Akhir.
 *
 * Migration dihentikan (bukan menghapus data diam-diam) bila masih ada riwayat dokumen berjenis
 * HARIAN atau MILESTONE, agar keputusan atas dokumen lama tersebut diambil secara sadar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tersisa = DB::table('report_documents')->whereIn('tipe_laporan', ['HARIAN', 'MILESTONE'])->count();

        if ($tersisa > 0) {
            throw new RuntimeException(
                "Masih ada {$tersisa} riwayat dokumen berjenis HARIAN/MILESTONE di tabel report_documents. "
                .'Hapus atau arsipkan dokumen tersebut terlebih dahulu, lalu jalankan migration ini kembali.'
            );
        }

        Schema::table('report_documents', function (Blueprint $table) {
            $table->enum('tipe_laporan', ['MINGGUAN', 'BULANAN', 'AKHIR'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('report_documents', function (Blueprint $table) {
            $table->enum('tipe_laporan', ['HARIAN', 'MINGGUAN', 'BULANAN', 'MILESTONE', 'AKHIR'])->change();
        });
    }
};
