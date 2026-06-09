<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'company_id', 'app_id', 'permission', 'granted', 'valid_from', 'valid_until'])]

class UserAppPermission extends Model
{
    use HasUuids;

    public $timestamps   = false;
    public $incrementing = false;
    protected $keyType   = 'string';

    protected function casts(): array
    {
        return [
            'granted'     => 'boolean',
            'valid_from'  => 'datetime',
            'valid_until' => 'datetime',
            'approved_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('next_id');
    }

    public function scopeActive(Builder $query): void
    {
        $query->current()
              ->where('granted', true)
              ->where('approval_status', '!=', 'pending')
              ->where(function (Builder $q): void {
                  $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
              });
    }

    public function scopeForContext(
        Builder $query,
        string  $userId,
        string  $companyId,
        string  $appId,
    ): void {
        $query->where('user_id', $userId)
              ->where('company_id', $companyId)
              ->where('app_id', $appId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function app(): BelongsTo
    {
        return $this->belongsTo(App::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function previousVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'prev_id');
    }
}
