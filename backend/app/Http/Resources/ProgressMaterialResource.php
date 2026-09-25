<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressMaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_material' => $this->nama_material,
            'jumlah' => (float) $this->jumlah,
            'unit_id' => $this->unit_id,
            'satuan' => $this->unit?->code ?? $this->satuan,
            'keterangan' => $this->keterangan,
        ];
    }
}
