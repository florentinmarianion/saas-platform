<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
#[Fillable(['name', 'slug', 'description', 'scope', 'company_id', 'default_permissions'])]

class Role extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    protected function casts(): array
    {
        return ['default_permissions' => 'array'];
    }

    public function scopeCurrent(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('next_id');
    }

    public function scopePlatform(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->current()->where('scope', 'platform');
    }

    public function scopeForCompany(
        \Illuminate\Database\Eloquent\Builder $query,
        string $companyId,
    ): void {
        $query->current()->where(function ($q) use ($companyId): void {
            $q->where('scope', 'platform')
              ->orWhere(function ($q2) use ($companyId): void {
                  $q2->where('scope', 'company')
                     ->where('company_id', $companyId);
              });
        });
    }

    public function platformPermissions(): array
    {
        return $this->default_permissions['platform'] ?? [];
    }

    public function appPermissionsFor(string $appSlug): array
    {
        return $this->default_permissions['apps'][$appSlug] ?? [];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
