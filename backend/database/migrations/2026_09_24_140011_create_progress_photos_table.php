<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('progress_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('progress_detail_id')->nullable()->constrained('progress_details')->nullOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('caption')->nullable();
            $table->timestamp('diunggah_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
