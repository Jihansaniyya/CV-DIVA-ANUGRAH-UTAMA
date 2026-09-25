<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'urutan' => $this->urutan,
            'nama_periode' => $this->nama_periode,
            'bulan_ke' => $this->bulan_ke,
            'minggu_ke' => $this->minggu_ke,
            'minggu_ke_bulan' => $this->minggu_ke_bulan,
            'tanggal_mulai' => $this->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $this->tanggal_selesai?->toDateString(),
        ];
    }
}
