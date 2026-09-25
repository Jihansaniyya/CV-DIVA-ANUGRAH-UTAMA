<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_item_id')->nullable()->constrained('work_items')->nullOnDelete();
            $table->enum('jenis_kendala', ['CUACA', 'MATERIAL', 'TENAGA_KERJA', 'PERALATAN', 'TEKNIS', 'LAINNYA'])->default('LAINNYA');
            $table->text('deskripsi');
            $table->text('alasan_keterlambatan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->enum('status', ['TERBUKA', 'DALAM_PENANGANAN', 'SELESAI'])->default('TERBUKA');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_issues');
    }
};
