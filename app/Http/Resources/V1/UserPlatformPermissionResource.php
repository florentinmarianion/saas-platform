<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPlatformPermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'permission'      => $this->permission,
            'granted'         => $this->granted,
            'approval_status' => $this->approval_status,
            'version'         => $this->version,
            'prev_id'         => $this->prev_id,
            'valid_until'     => $this->valid_until,
            'granted_by'      => $this->granted_by,
            'created_at'      => $this->created_at,
        ];
    }
}
