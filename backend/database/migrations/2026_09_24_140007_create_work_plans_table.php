<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_item_id')->constrained('work_items')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            // Target volume pekerjaan yang direncanakan pada periode tersebut.
            $table->decimal('target_volume', 15, 3)->default(0);
            // Persentase terhadap volume total pekerjaan tersebut.
            $table->decimal('target_persentase', 9, 4)->default(0);
            // Kontribusi terhadap progres proyek = target_persentase x bobot / 100.
            $table->decimal('target_bobot', 9, 4)->default(0);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['work_item_id', 'period_id']);
            $table->index(['project_id', 'period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_plans');
    }
};
