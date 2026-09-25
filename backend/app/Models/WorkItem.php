<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'work_category_id',
        'unit_id',
        'uraian_pekerjaan',
        'volume',
        'harga_satuan',
        'harga_pekerjaan',
        'bobot',
        'bobot_manual',
        'waktu_mulai',
        'waktu_selesai',
        'urutan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'volume' => 'decimal:3',
            'harga_satuan' => 'decimal:2',
            'harga_pekerjaan' => 'decimal:2',
            'bobot' => 'decimal:4',
            'bobot_manual' => 'decimal:4',
            'waktu_mulai' => 'date',
            'waktu_selesai' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkCategory::class, 'work_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function workPlans(): HasMany
    {
        return $this->hasMany(WorkPlan::class);
    }

    public function progressDetails(): HasMany
    {
        return $this->hasMany(ProgressDetail::class);
    }

    /** Total volume yang sudah terealisasi dari seluruh laporan yang sudah dikirim. */
    public function volumeRealisasi(): float
    {
        return (float) $this->progressDetails()
            ->whereHas('report', fn ($q) => $q->where('status', 'DIKIRIM'))
            ->sum('volume_realisasi');
    }
}
