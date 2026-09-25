<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'work_item_id',
        'period_id',
        'target_volume',
        'target_persentase',
        'target_bobot',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'target_volume' => 'decimal:3',
            'target_persentase' => 'decimal:4',
            'target_bobot' => 'decimal:4',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
