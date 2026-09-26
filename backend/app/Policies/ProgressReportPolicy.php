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

    /** Kontraktor hanya memantau laporan yang sudah dikirim QS. */
    public function view(User $user, ProgressReport $report): bool
    {
        if ($user->isKontraktor() && $report->status !== ReportStatus::DIKIRIM) {
            return false;
        }

        return $user->is_active && $report->project->isAccessibleBy($user);
    }

    /** Hanya QS yang boleh membuat laporan; Admin hanya memantau. */
    public function create(User $user): bool
    {
        return $user->isQs();
    }

    /** QS hanya dapat mengubah laporan miliknya yang masih draf. */
    public function update(User $user, ProgressReport $report): bool
    {
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
        return $user->isQs() && $report->user_id === $user->id;
    }
}
