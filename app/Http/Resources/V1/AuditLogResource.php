<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'action'      => $this->action,
            'entity_type' => $this->entity_type,
            'entity_id'   => $this->entity_id,
            'payload'     => $this->payload,
            'ip_address'  => $this->ip_address,
            'created_at'  => $this->created_at,

            'user' => $this->whenLoaded(
                'user',
                fn() => [
                    'id'   => $this->user->id,
                    'name' => $this->user->name,
                ]
            ),
        ];
    }
}
