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
        'client_request_id',
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

    public function issues(): HasMany
    {
        return $this->hasMany(ProgressIssue::class);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::DIKIRIM);
    }

    /**
     * Laporan yang memakai volume pekerjaan: laporan terkirim, ditambah draf milik QS yang masih
     * ditugaskan pada proyeknya. Draf QS yang sudah diganti tetap tersimpan, tetapi tidak lagi
     * mengurangi sisa volume karena tidak mungkin dikirim olehnya.
     */
    public function scopeMemakaiVolume(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('progress_reports.status', ReportStatus::DIKIRIM->value)
                ->orWhere(function (Builder $draf) {
                    $draf->where('progress_reports.status', ReportStatus::DRAFT->value)
                        ->where(function (Builder $akses) {
                            $akses->whereExists(fn ($sub) => $sub->selectRaw('1')->from('projects')
                                ->whereColumn('projects.id', 'progress_reports.project_id')
                                ->whereColumn('projects.qs_user_id', 'progress_reports.user_id'))
                                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('project_assignments')
                                    ->whereColumn('project_assignments.project_id', 'progress_reports.project_id')
                                    ->whereColumn('project_assignments.user_id', 'progress_reports.user_id'));
                        });
                });
        });
    }
}
