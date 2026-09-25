<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Nomor pekerjaan dan nomor proyek tidak lagi dipakai; identitas proyek cukup nomor SPK. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['nomor_pekerjaan', 'nomor_proyek']);
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('nomor_pekerjaan', 120)->nullable()->after('nomor_spk');
            $table->string('nomor_proyek', 120)->nullable()->after('nomor_pekerjaan');
        });
    }
};
