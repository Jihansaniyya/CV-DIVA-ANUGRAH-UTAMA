<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // Periode diisi otomatis berdasarkan tanggal_laporan (lihat ProgressService).
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->date('tanggal_laporan');
            $table->text('keterangan')->nullable();
            $table->string('lokasi')->nullable();
            $table->string('cuaca', 50)->nullable();
            $table->enum('status', ['DRAFT', 'DIKIRIM'])->default('DRAFT');
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'tanggal_laporan']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};
