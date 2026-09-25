<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'work_category_id' => $this->work_category_id,
            'kategori' => new WorkCategoryResource($this->whenLoaded('category')),
            'unit_id' => $this->unit_id,
            'satuan' => $this->unit?->code,
            'uraian_pekerjaan' => $this->uraian_pekerjaan,
            'volume' => (float) $this->volume,
            'harga_satuan' => $this->harga_satuan !== null ? (float) $this->harga_satuan : null,
            'harga_pekerjaan' => $this->harga_pekerjaan !== null ? (float) $this->harga_pekerjaan : null,
            'bobot' => (float) $this->bobot,
            'bobot_manual' => $this->bobot_manual !== null ? (float) $this->bobot_manual : null,
            'waktu_mulai' => $this->waktu_mulai?->toDateString(),
            'waktu_selesai' => $this->waktu_selesai?->toDateString(),
            'urutan' => $this->urutan,
            'keterangan' => $this->keterangan,
            'volume_realisasi' => $this->when(isset($this->volume_realisasi), fn () => (float) $this->volume_realisasi),
            'persentase_realisasi' => $this->when(isset($this->persentase_realisasi), fn () => (float) $this->persentase_realisasi),
        ];
    }
}
