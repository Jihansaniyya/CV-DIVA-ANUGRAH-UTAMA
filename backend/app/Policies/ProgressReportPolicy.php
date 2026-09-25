<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\ProgressReport;
use App\Models\User;

class ProgressReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ProgressReport $report): bool
    {
        return $user->is_active && $report->project->isAccessibleBy($user);
    }

    /** Hanya QS yang ditugaskan pada proyek (atau Admin) yang boleh membuat laporan. */
    public function create(User $user): bool
    {
        return $user->isQs() || $user->isAdmin();
    }

    /** Laporan yang sudah dikirim tidak dapat diubah kecuali oleh Admin. */
    public function update(User $user, ProgressReport $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isQs()
            && $report->user_id === $user->id
            && $report->status === ReportStatus::DRAFT;
    }

    public function delete(User $user, ProgressReport $report): bool
    {
        return $this->update($user, $report);
    }

    public function submit(User $user, ProgressReport $report): bool
    {
        return $user->isAdmin() || ($user->isQs() && $report->user_id === $user->id);
    }
}
