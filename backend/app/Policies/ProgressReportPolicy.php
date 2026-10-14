<?php

namespace App\Policies;

use App\Enums\ReportStatus;
use App\Models\ProgressReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

        return $user->is_active && $report->project !== null && $report->project->isAccessibleBy($user);
    }

    /** Hanya QS aktif yang boleh membuat laporan; Admin hanya memantau. */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isQs();
    }

    /**
     * QS hanya dapat mengubah laporan miliknya yang masih draf, dan hanya selama ia masih ditugaskan
     * pada proyek tersebut. Draf milik QS yang sudah diganti tetap tersimpan, tetapi tidak dapat lagi
     * diubah, dikirim, atau dihapus olehnya.
     */
    public function update(User $user, ProgressReport $report): Response
    {
        if (! $user->is_active || ! $user->isQs() || $report->user_id !== $user->id) {
            return Response::deny('Hanya QS pembuat laporan yang dapat mengubah laporan ini.');
        }

        if ($report->status !== ReportStatus::DRAFT) {
            return Response::deny('Laporan yang sudah dikirim tidak dapat diubah, dikirim ulang, atau dihapus.');
        }

        if ($report->project === null || ! $report->project->isAccessibleBy($user)) {
            return Response::deny('Anda tidak lagi ditugaskan pada proyek ini, sehingga draf ini tidak dapat diubah.');
        }

        return Response::allow();
    }

    public function delete(User $user, ProgressReport $report): Response
    {
        return $this->update($user, $report);
    }

    public function submit(User $user, ProgressReport $report): Response
    {
        return $this->update($user, $report);
    }
}
