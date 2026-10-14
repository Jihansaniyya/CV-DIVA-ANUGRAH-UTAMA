<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Menjaga tabel penugasan tetap sama dengan QS penanggung jawab proyek (`projects.qs_user_id`).
 *
 * Hak akses QS dibaca dari `qs_user_id` maupun `project_assignments`, sehingga penugasan utama milik
 * QS lama wajib dicabut saat QS diganti. Bila tidak, QS lama tetap dapat membuka proyek dan
 * membuat laporan baru. Laporan yang sudah dibuat QS lama tidak disentuh.
 */
class ProjectAssignmentService
{
    public function sinkronkanQs(Project $project): void
    {
        DB::transaction(function () use ($project) {
            // Penugasan utama selain QS yang sekarang (termasuk sisa pergantian sebelumnya) dicabut.
            $project->assignments()
                ->where('is_primary', true)
                ->when($project->qs_user_id, fn ($q) => $q->where('user_id', '!=', $project->qs_user_id))
                ->delete();

            if (! $project->qs_user_id) {
                return;
            }

            $project->assignments()->updateOrCreate(
                ['user_id' => $project->qs_user_id],
                ['peran' => 'QS', 'is_primary' => true]
            );
        });
    }
}
