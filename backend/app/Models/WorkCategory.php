<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCategory extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'kode', 'nama', 'urutan'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class)->orderBy('urutan');
    }
}
