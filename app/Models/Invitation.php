<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'invited_by', 'email', 'name', 'token_hash', 'permissions_snapshot', 'role_label', 'expires_at'])]
#[Hidden(['token_hash'])]

class Invitation extends Model
{
    use HasUuids;

    public $timestamps   = false;
    public $incrementing = false;
    protected $keyType   = 'string';

    protected function casts(): array
    {
        return [
            'permissions_snapshot' => 'array',
            'expires_at'           => 'datetime',
            'accepted_at'          => 'datetime',
            'created_at'           => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
