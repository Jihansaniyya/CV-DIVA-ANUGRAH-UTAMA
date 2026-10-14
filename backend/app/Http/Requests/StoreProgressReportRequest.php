<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ProgressReportRules;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

class StoreProgressReportRequest extends FormRequest
{
    use ProgressReportRules;

    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || ! $user->is_active || ! $user->isQs()) {
            return false;
        }

        $project = Project::find($this->input('project_id'));

        return $project !== null && $project->isAccessibleBy($user);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'tanggal_laporan' => ['required', 'date_format:Y-m-d'],
            // Dibuat sekali per isian form; pengiriman ulang dengan nilai sama tidak membuat laporan baru.
            'client_request_id' => ['nullable', 'uuid'],
            ...$this->aturanIsiLaporan(detailWajib: true),
        ];
    }
}
