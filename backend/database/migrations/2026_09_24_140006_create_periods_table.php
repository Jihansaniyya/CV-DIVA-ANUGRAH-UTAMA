<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->string('nama_periode', 60);
            $table->unsignedSmallInteger('bulan_ke')->default(1);
            $table->unsignedSmallInteger('minggu_ke')->default(1);
            $table->unsignedSmallInteger('minggu_ke_bulan')->default(1);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->timestamps();

            $table->unique(['project_id', 'urutan']);
            $table->index(['project_id', 'bulan_ke']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
