<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ProgressReportRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgressReportRequest extends FormRequest
{
    use ProgressReportRules;

    /** Proyek diambil dari laporan yang diubah; laporan tidak dapat dipindahkan ke proyek lain. */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active || ! $user->isQs()) {
            return false;
        }

        $project = $this->route('progress')?->project;

        return $project !== null && $project->isAccessibleBy($user);
    }

    public function rules(): array
    {
        return [
            'tanggal_laporan' => ['sometimes', 'required', 'date_format:Y-m-d'],
            ...$this->aturanIsiLaporan(detailWajib: false),
        ];
    }
}
