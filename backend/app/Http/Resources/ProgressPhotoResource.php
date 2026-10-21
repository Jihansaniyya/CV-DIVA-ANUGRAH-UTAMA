<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'progress_report_id' => $this->progress_report_id,
            'progress_detail_id' => $this->progress_detail_id,
            // Pekerjaan pemilik foto; null untuk foto lama yang belum dikaitkan ke pekerjaan.
            'work_item_id' => $this->whenLoaded('detail', fn () => $this->detail?->work_item_id),
            'uraian_pekerjaan' => $this->whenLoaded('detail', fn () => $this->detail?->workItem?->uraian_pekerjaan),
            'url' => $this->url(),
            'caption' => $this->caption,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'diunggah_pada' => $this->diunggah_pada?->toIso8601String(),
        ];
    }
}
