<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'work_item_id' => $this->work_item_id,
            'period_id' => $this->period_id,
            'uraian_pekerjaan' => $this->workItem?->uraian_pekerjaan,
            'nama_periode' => $this->period?->nama_periode,
            'target_volume' => (float) $this->target_volume,
            'target_persentase' => (float) $this->target_persentase,
            'target_bobot' => (float) $this->target_bobot,
            'catatan' => $this->catatan,
        ];
    }
}
