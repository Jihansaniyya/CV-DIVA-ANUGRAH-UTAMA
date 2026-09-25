<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ReportDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'nama_proyek' => $this->project?->nama_proyek,
            'tipe_laporan' => $this->tipe_laporan->value,
            'format' => $this->format,
            'periode_mulai' => $this->periode_mulai?->toDateString(),
            'periode_selesai' => $this->periode_selesai?->toDateString(),
            'file_name' => $this->file_name,
            'url' => Storage::disk('public')->url($this->file_path),
            'digenerate_pada' => $this->digenerate_pada?->toIso8601String(),
            'dibuat_oleh' => $this->user?->name,
        ];
    }
}
