<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'period_id',
        'nama',
        'deskripsi',
        'tanggal_target',
        'target_persentase',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_target' => 'date',
            'target_persentase' => 'decimal:4',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }
}
