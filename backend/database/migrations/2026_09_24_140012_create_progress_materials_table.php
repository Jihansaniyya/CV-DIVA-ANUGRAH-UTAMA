<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->string('nama_material', 150);
            $table->decimal('jumlah', 15, 3)->default(0);
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->string('satuan', 30)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_materials');
    }
};
