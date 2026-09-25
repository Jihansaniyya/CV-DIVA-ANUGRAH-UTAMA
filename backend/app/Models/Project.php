<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nama_proyek',
        'nomor_spk',
        'lokasi',
        'sumber_dana',
        'tahun_anggaran',
        'tanggal_spk',
        'tanggal_mulai',
        'tanggal_selesai',
        'jangka_waktu_hari',
        'kontraktor_pelaksana',
        'konsultan_pengawas',
        'nama_site_engineer',
        'nama_pelaksana_lapangan',
        'qs_user_id',
        'created_by',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_spk' => 'date',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status' => ProjectStatus::class,
        ];
    }

    public function qs(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qs_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_assignments')
            ->withPivot(['peran', 'is_primary'])
            ->withTimestamps();
    }

    public function workCategories(): HasMany
    {
        return $this->hasMany(WorkCategory::class)->orderBy('urutan');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class)->orderBy('urutan');
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class)->orderBy('urutan');
    }

    public function workPlans(): HasMany
    {
        return $this->hasMany(WorkPlan::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('tanggal_target');
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function reportDocuments(): HasMany
    {
        return $this->hasMany(ReportDocument::class);
    }

    /** Batasi query ke proyek yang boleh diakses user (QS hanya proyek yang ditugaskan). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->isKontraktor()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('qs_user_id', $user->id)
                ->orWhereHas('assignments', fn (Builder $a) => $a->where('user_id', $user->id));
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($user->isAdmin() || $user->isKontraktor()) {
            return true;
        }

        return $this->qs_user_id === $user->id
            || $this->assignments()->where('user_id', $user->id)->exists();
    }
}
