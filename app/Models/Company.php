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

#[Fillable(['slug', 'name', 'legal_name', 'vat_number', 'country', 'logo_url', 'settings'])]

class Company extends Model
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
            'settings'    => 'array',
            'approved_at' => 'datetime',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
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

    public function apps(): BelongsToMany
    {
        return $this->belongsToMany(App::class, 'company_apps')
                    ->using(CompanyApp::class)
                    ->withPivot(['id', 'status', 'config', 'trial_ends_at'])
                    ->withTimestamps();
    }

    public function companyApps(): HasMany
    {
        return $this->hasMany(CompanyApp::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
