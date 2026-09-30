<?php

namespace App\Http\Resources;

use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'work_item_id' => $this->work_item_id,
            'pekerjaan' => $this->workItem?->uraian_pekerjaan,
            'jenis_kendala' => $this->jenis_kendala,
            'deskripsi' => ProgressService::gabungKendala($this->deskripsi, $this->alasan_keterlambatan),
            'tindak_lanjut' => $this->tindak_lanjut,
            'status' => $this->status,
        ];
    }
}
