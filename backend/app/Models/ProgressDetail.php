<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'work_item_id',
        'volume_realisasi',
        'persentase_realisasi',
        'bobot_realisasi',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'volume_realisasi' => 'decimal:3',
            'persentase_realisasi' => 'decimal:4',
            'bobot_realisasi' => 'decimal:4',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }
}
