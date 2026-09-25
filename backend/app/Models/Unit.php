<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'name'];

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }
}
