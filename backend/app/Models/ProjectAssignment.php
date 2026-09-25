<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['project_id', 'user_id', 'peran', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
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
