<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kode', 10)->nullable();
            $table->string('nama', 150);
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();

            $table->index(['project_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_categories');
    }
};
