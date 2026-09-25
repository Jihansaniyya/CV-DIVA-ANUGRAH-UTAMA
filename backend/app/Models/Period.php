<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'urutan',
        'nama_periode',
        'bulan_ke',
        'minggu_ke',
        'minggu_ke_bulan',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workPlans(): HasMany
    {
        return $this->hasMany(WorkPlan::class);
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class);
    }
}
