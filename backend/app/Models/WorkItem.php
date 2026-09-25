<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'period_mulai_id',
        'period_selesai_id',
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

    public function periodMulai(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_mulai_id');
    }

    public function periodSelesai(): BelongsTo
    {
        return $this->belongsTo(Period::class, 'period_selesai_id');
    }

    /**
     * Periode aktif pekerjaan: M-mulai s/d M-selesai.
     * Bila rentang belum ditetapkan, seluruh periode proyek dianggap aktif.
     */
    public function activePeriods(): Collection
    {
        $mulai = $this->periodMulai?->urutan ?? 1;
        $selesai = $this->periodSelesai?->urutan ?? PHP_INT_MAX;

        return Period::where('project_id', $this->project_id)
            ->whereBetween('urutan', [$mulai, $selesai])
            ->orderBy('urutan')
            ->get();
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
