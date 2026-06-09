<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entity_type', 'entity_id', 'action', 'requested_by', 'status', 'payload', 'rejection_reason', 'requester_note', 'expires_at'])]

class ApprovalRequest extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'reviewed_at' => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending')
              ->where('expires_at', '>', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
