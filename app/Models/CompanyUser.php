<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['company_id', 'user_id', 'role_label', 'is_owner', 'status'])]

class CompanyUser extends Pivot
{
    use HasUuids;

    public $table        = 'company_users';
    public $incrementing = false;
    protected $keyType   = 'string';
    public $timestamps   = true;

    protected function casts(): array
    {
        return [
            'is_owner'    => 'boolean',
            'approved_at' => 'datetime',
            'joined_at'   => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
