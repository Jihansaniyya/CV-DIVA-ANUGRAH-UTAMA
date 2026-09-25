<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('nama_proyek');
            $table->string('nomor_spk', 120)->nullable();
            $table->string('nomor_pekerjaan', 120)->nullable();
            $table->string('nomor_proyek', 120)->nullable();
            $table->string('lokasi');
            $table->string('sumber_dana', 150)->nullable();
            $table->year('tahun_anggaran')->nullable();
            $table->date('tanggal_spk')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            // Dihitung dari tanggal_mulai s/d tanggal_selesai (lihat ProjectScheduleService).
            $table->unsignedSmallInteger('jangka_waktu_hari')->nullable();
            $table->string('kontraktor_pelaksana', 150)->nullable();
            $table->string('konsultan_pengawas', 150)->nullable();
            $table->string('nama_site_engineer', 120)->nullable();
            $table->string('nama_pelaksana_lapangan', 120)->nullable();
            $table->foreignId('qs_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['BELUM_DIMULAI', 'BERJALAN', 'SELESAI', 'TERLAMBAT'])->default('BELUM_DIMULAI');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('qs_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
