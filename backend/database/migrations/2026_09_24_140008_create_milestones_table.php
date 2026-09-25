<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('periods')->nullOnDelete();
            $table->string('nama', 150);
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_target');
            $table->decimal('target_persentase', 9, 4)->default(0);
            $table->enum('status', ['BELUM_TERCAPAI', 'TERCAPAI', 'TERLAMBAT'])->default('BELUM_TERCAPAI');
            $table->timestamps();

            $table->index(['project_id', 'tanggal_target']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
