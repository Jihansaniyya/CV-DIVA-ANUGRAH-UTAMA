<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bobot pekerjaan tidak lagi diinput manual oleh Admin.
 * Seluruh bobot dihitung sistem dari harga pekerjaan terhadap total harga proyek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn('bobot_manual');
        });
    }

    public function down(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            $table->decimal('bobot_manual', 9, 4)->nullable()->after('bobot');
        });
    }
};
