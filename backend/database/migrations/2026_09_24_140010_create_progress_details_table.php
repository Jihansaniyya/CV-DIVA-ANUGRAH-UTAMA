<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_item_id')->constrained('work_items')->cascadeOnDelete();
            // Volume yang dikerjakan pada laporan ini (bukan kumulatif).
            $table->decimal('volume_realisasi', 15, 3)->default(0);
            // volume_realisasi / volume rencana x 100
            $table->decimal('persentase_realisasi', 9, 4)->default(0);
            // persentase_realisasi x bobot / 100  (kontribusi ke progres proyek)
            $table->decimal('bobot_realisasi', 9, 4)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['progress_report_id', 'work_item_id']);
            $table->index('work_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_details');
    }
};
