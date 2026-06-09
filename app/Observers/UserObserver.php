<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\AuditService;

class UserObserver
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function created(User $user): void
    {
        $this->audit->record(
            action:     'user.created',
            entityType: 'User',
            entityId:   $user->id,
            payload:    [
                'name'  => $user->name,
                'email' => $user->email,
            ],
        );
    }

    public function updated(User $user): void
    {
        $changes = $user->getChanges();

        // Never log password changes in payload
        unset($changes['password_hash'], $changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $this->audit->record(
            action:     'user.updated',
            entityType: 'User',
            entityId:   $user->id,
            payload:    [
                'changes' => $changes,
                'original' => array_intersect_key(
                    $user->getOriginal(),
                    $changes,
                ),
            ],
        );
    }

    public function deleted(User $user): void
    {
        $this->audit->record(
            action:     'user.deleted',
            entityType: 'User',
            entityId:   $user->id,
            payload:    ['email' => $user->email],
        );
    }

    public function restored(User $user): void
    {
        $this->audit->record(
            action:     'user.restored',
            entityType: 'User',
            entityId:   $user->id,
            payload:    ['email' => $user->email],
        );
    }
}
