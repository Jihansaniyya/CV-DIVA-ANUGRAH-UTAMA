<?php

namespace App\Models;

use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'user_id',
        'tipe_laporan',
        'format',
        'periode_mulai',
        'periode_selesai',
        'file_path',
        'file_name',
        'digenerate_pada',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'digenerate_pada' => 'datetime',
            'tipe_laporan' => ReportType::class,
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
}
