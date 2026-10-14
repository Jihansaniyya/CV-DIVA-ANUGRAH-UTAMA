<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identitas unik permintaan dari form progres. Permintaan yang dikirim ulang (mis. setelah koneksi
 * terputus sebelum respons diterima) memakai identitas yang sama sehingga tidak membuat laporan ganda.
 * Kolom boleh kosong agar laporan lama tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->after('status');
            $table->unique(['user_id', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('progress_reports', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_request_id']);
            $table->dropColumn('client_request_id');
        });
    }
};
