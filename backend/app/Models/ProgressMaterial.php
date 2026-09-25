<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'progress_report_id',
        'nama_material',
        'jumlah',
        'unit_id',
        'satuan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return ['jumlah' => 'decimal:3'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ProgressReport::class, 'progress_report_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
