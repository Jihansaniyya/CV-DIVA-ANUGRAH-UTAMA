<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sebelumnya pergantian QS hanya menambah penugasan QS baru tanpa mencabut penugasan QS lama,
 * sehingga QS lama tetap dapat mengakses proyek. Migrasi ini hanya menghapus baris penugasan utama
 * yang tidak lagi sama dengan `projects.qs_user_id`. Laporan progres, foto, dan data proyek tidak
 * diubah. Tidak dapat dibatalkan karena penugasan yang dicabut memang tidak lagi berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('project_assignments')
            ->where('is_primary', true)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('projects')
                    ->whereColumn('projects.id', 'project_assignments.project_id')
                    ->whereColumn('projects.qs_user_id', 'project_assignments.user_id');
            })
            ->delete();
    }

    public function down(): void
    {
        // Penugasan QS lama sengaja tidak dipulihkan.
    }
};
