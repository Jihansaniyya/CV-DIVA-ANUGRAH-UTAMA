<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProgressPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'progress_detail_id',
        'file_path',
        'original_name',
        'file_size',
        'caption',
        'diunggah_pada',
    ];

    protected function casts(): array
    {
        return ['diunggah_pada' => 'datetime'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(ProgressDetail::class, 'progress_detail_id');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
