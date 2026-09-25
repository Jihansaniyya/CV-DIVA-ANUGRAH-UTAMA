<?php

namespace App\Models;

use App\Enums\RoleCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'role_id',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Proyek yang dipegang user ini sebagai QS penanggung jawab utama. */
    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'qs_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_assignments')
            ->withPivot(['peran', 'is_primary'])
            ->withTimestamps();
    }

    public function progressReports(): HasMany
    {
        return $this->hasMany(ProgressReport::class);
    }

    public function roleCode(): ?RoleCode
    {
        return $this->role ? RoleCode::tryFrom($this->role->code) : null;
    }

    public function hasRole(RoleCode|string $role): bool
    {
        $code = $role instanceof RoleCode ? $role->value : $role;

        return $this->role?->code === $code;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(RoleCode::ADMIN);
    }

    public function isQs(): bool
    {
        return $this->hasRole(RoleCode::QS);
    }

    public function isKontraktor(): bool
    {
        return $this->hasRole(RoleCode::KONTRAKTOR);
    }
}
