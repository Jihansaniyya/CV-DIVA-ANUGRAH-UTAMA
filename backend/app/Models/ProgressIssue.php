<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'work_item_id',
        'jenis_kendala',
        'deskripsi',
        'alasan_keterlambatan',
        'tindak_lanjut',
        'status',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }
}
