<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sebelumnya kendala draf yang jenisnya belum dipilih otomatis disimpan sebagai LAINNYA, sehingga saat
 * dikirim backend tidak dapat membedakan "belum dipilih" dari "Lainnya". Kolom dibuat boleh kosong;
 * isi yang sudah ada tidak berubah. Laporan DIKIRIM tetap wajib memiliki jenis kendala (ProgressService).
 */
return new class extends Migration
{
    private const JENIS = ['CUACA', 'MATERIAL', 'TENAGA_KERJA', 'PERALATAN', 'TEKNIS', 'LAINNYA'];

    public function up(): void
    {
        Schema::table('progress_issues', function (Blueprint $table) {
            $table->enum('jenis_kendala', self::JENIS)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // Baris tanpa jenis dikembalikan ke nilai bawaan lama agar kolom dapat kembali NOT NULL.
        DB::table('progress_issues')->whereNull('jenis_kendala')->update(['jenis_kendala' => 'LAINNYA']);

        Schema::table('progress_issues', function (Blueprint $table) {
            $table->enum('jenis_kendala', self::JENIS)->default('LAINNYA')->change();
        });
    }
};
