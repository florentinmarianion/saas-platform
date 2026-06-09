<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserAppPermission;
use App\Services\AuditService;

class UserAppPermissionObserver
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function created(UserAppPermission $permission): void
    {
        $action = $permission->granted
            ? 'permission.granted'
            : 'permission.revoked';

        $this->audit->record(
            action:     $action,
            entityType: 'UserAppPermission',
            entityId:   $permission->id,
            payload:    [
                'user_id'         => $permission->user_id,
                'app_id'          => $permission->app_id,
                'permission'      => $permission->permission,
                'granted'         => $permission->granted,
                'approval_status' => $permission->approval_status,
                'version'         => $permission->version,
                'prev_id'         => $permission->prev_id,
            ],
            companyId: $permission->company_id,
            appId:     $permission->app_id,
        );
    }
}
