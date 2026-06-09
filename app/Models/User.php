<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['email', 'name', 'password_hash', 'avatar_url', 'locale', 'timezone'])]
#[Hidden(['password_hash'])]

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected function casts(): array
    {
        return ['password_hash' => 'hashed'];
    }

    // Laravel expects 'password' column by default — we override it
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // ── Scopes ───────────────────────────────────────────────────────
    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('next_id');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->current()->where('status', 'active');
    }

    // ── Relationships ────────────────────────────────────────────────
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
                    ->using(CompanyUser::class)
                    ->withPivot([
                        'id', 'role_label', 'is_owner',
                        'status', 'approval_status', 'joined_at',
                    ])
                    ->withTimestamps();
    }

    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    public function platformPermissions(): HasMany
    {
        return $this->hasMany(UserPlatformPermission::class)
                    ->whereNull('next_id')
                    ->where('granted', true)
                    ->where('approval_status', '!=', 'pending');
    }

    public function appPermissions(): HasMany
    {
        return $this->hasMany(UserAppPermission::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}
