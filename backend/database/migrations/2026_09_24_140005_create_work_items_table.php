<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_category_id')->nullable()->constrained('work_categories')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('uraian_pekerjaan');
            $table->decimal('volume', 15, 3)->default(0);
            // Harga bersifat opsional: bila tersedia, bobot dihitung dari harga pekerjaan.
            $table->decimal('harga_satuan', 18, 2)->nullable();
            $table->decimal('harga_pekerjaan', 18, 2)->nullable();
            // Bobot efektif (%) hasil perhitungan WeightCalculatorService.
            $table->decimal('bobot', 9, 4)->default(0);
            // Bobot manual dipakai hanya bila proyek tidak memakai data harga.
            $table->decimal('bobot_manual', 9, 4)->nullable();
            $table->date('waktu_mulai')->nullable();
            $table->date('waktu_selesai')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_items');
    }
};
