<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgressReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'period_id',
        'tanggal_laporan',
        'keterangan',
        'lokasi',
        'cuaca',
        'status',
        'dikirim_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_laporan' => 'date',
            'dikirim_pada' => 'datetime',
            'status' => ReportStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function details(): HasMany
    {
        return $this->hasMany(ProgressDetail::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ProgressPhoto::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ProgressMaterial::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProgressIssue::class);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::DIKIRIM);
    }
}
