<?php

namespace App\Http\Requests\Concerns;

use App\Models\Period;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Periode mulai/selesai pekerjaan harus berupa periode proyek yang sama dan berurutan. */
trait ValidatesWorkItemPeriods
{
    protected function periodRule(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return ['integer', Rule::exists('periods', 'id')->where('project_id', $project->id)];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['period_mulai_id', 'period_selesai_id'])) {
                    return;
                }

                $workItem = $this->route('workItem');
                $mulaiId = $this->input('period_mulai_id', $workItem?->period_mulai_id);
                $selesaiId = $this->input('period_selesai_id', $workItem?->period_selesai_id);

                if (! $mulaiId || ! $selesaiId) {
                    return;
                }

                $urutan = Period::whereIn('id', [$mulaiId, $selesaiId])->pluck('urutan', 'id');

                if (($urutan[$selesaiId] ?? 0) < ($urutan[$mulaiId] ?? 0)) {
                    $validator->errors()->add('period_selesai_id', 'Periode selesai tidak boleh sebelum periode mulai.');
                }
            },
        ];
    }
}
