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
            'url' => $this->url(),
            'caption' => $this->caption,
            'original_name' => $this->original_name,
            'file_size' => $this->file_size,
            'diunggah_pada' => $this->diunggah_pada?->toIso8601String(),
        ];
    }
}
