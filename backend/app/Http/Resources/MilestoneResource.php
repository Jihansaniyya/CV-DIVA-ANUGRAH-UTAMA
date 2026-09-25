<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilestoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'period_id' => $this->period_id,
            'periode' => $this->period?->nama_periode,
            'nama' => $this->nama,
            'deskripsi' => $this->deskripsi,
            'tanggal_target' => $this->tanggal_target?->toDateString(),
            'target_persentase' => (float) $this->target_persentase,
            'status' => $this->status,
        ];
    }
}
