<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['slug', 'module', 'name', 'description', 'icon', 'semver', 'declared_permissions', 'metadata'])]
class App extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected function casts(): array
    {
        return [
            'declared_permissions' => 'array',
            'metadata'             => 'array',
            'approved_at'          => 'datetime',
        ];
    }

    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('next_id');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->current()->where('status', 'active');
    }

    public function declaresPermission(string $permission): bool
    {
        return in_array($permission, $this->declared_permissions ?? [], strict: true);
    }

    public function isSensitivePermission(string $permission): bool
    {
        $sensitive = $this->metadata['sensitive_permissions'] ?? [];
        return in_array($permission, $sensitive, strict: true);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_apps')
                    ->using(CompanyApp::class)
                    ->withPivot(['id', 'status', 'config', 'trial_ends_at'])
                    ->withTimestamps();
    }

    public function companyApps(): HasMany
    {
        return $this->hasMany(CompanyApp::class);
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserAppPermission::class);
    }
}
