<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'email'                => $this->email,
            'name'                 => $this->name,
            'role_label'           => $this->role_label,
            'status'               => $this->status,
            'permissions_snapshot' => $this->permissions_snapshot,
            'expires_at'           => $this->expires_at,
            'accepted_at'          => $this->accepted_at,
            'created_at'           => $this->created_at,

            'invited_by' => $this->whenLoaded(
                'invitedBy',
                fn() => [
                    'id'   => $this->invitedBy->id,
                    'name' => $this->invitedBy->name,
                ]
            ),
        ];
    }
}
