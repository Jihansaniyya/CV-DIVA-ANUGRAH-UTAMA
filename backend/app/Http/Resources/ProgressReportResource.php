<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'nama_proyek' => $this->project?->nama_proyek,
            'user_id' => $this->user_id,
            'pelapor' => $this->user?->name,
            'period_id' => $this->period_id,
            'periode' => $this->period?->nama_periode,
            'tanggal_laporan' => $this->tanggal_laporan?->toDateString(),
            'keterangan' => $this->keterangan,
            'lokasi' => $this->lokasi,
            'cuaca' => $this->cuaca,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'dikirim_pada' => $this->dikirim_pada?->toIso8601String(),
            'total_bobot_realisasi' => round((float) $this->details->sum('bobot_realisasi'), 4),
            'detail' => ProgressDetailResource::collection($this->whenLoaded('details')),
            'foto' => ProgressPhotoResource::collection($this->whenLoaded('photos')),
            'material' => ProgressMaterialResource::collection($this->whenLoaded('materials')),
            'kendala' => ProgressIssueResource::collection($this->whenLoaded('issues')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
