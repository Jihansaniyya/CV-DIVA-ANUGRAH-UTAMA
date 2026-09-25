<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('tipe_laporan', ['HARIAN', 'MINGGUAN', 'BULANAN', 'MILESTONE']);
            $table->enum('format', ['EXCEL', 'WORD']);
            $table->date('periode_mulai')->nullable();
            $table->date('periode_selesai')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamp('digenerate_pada');
            $table->timestamps();

            $table->index(['project_id', 'tipe_laporan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_documents');
    }
};
