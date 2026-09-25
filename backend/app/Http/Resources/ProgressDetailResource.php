<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_item_id' => $this->work_item_id,
            'uraian_pekerjaan' => $this->workItem?->uraian_pekerjaan,
            'satuan' => $this->workItem?->unit?->code,
            'volume_rencana' => (float) ($this->workItem?->volume ?? 0),
            'volume_realisasi' => (float) $this->volume_realisasi,
            'persentase_realisasi' => (float) $this->persentase_realisasi,
            'bobot_realisasi' => (float) $this->bobot_realisasi,
            'keterangan' => $this->keterangan,
        ];
    }
}
