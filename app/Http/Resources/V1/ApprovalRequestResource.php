<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApprovalRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'entity_type'      => $this->entity_type,
            'entity_id'        => $this->entity_id,
            'action'           => $this->action,
            'status'           => $this->status,
            'payload'          => $this->payload,
            'requester_note'   => $this->requester_note,
            'rejection_reason' => $this->rejection_reason,
            'expires_at'       => $this->expires_at,
            'reviewed_at'      => $this->reviewed_at,
            'created_at'       => $this->created_at,

            'requested_by' => $this->whenLoaded(
                'requestedBy',
                fn() => [
                    'id'   => $this->requestedBy->id,
                    'name' => $this->requestedBy->name,
                ]
            ),

            'reviewed_by' => $this->whenLoaded(
                'reviewedBy',
                fn() => [
                    'id'   => $this->reviewedBy->id,
                    'name' => $this->reviewedBy->name,
                ]
            ),
        ];
    }
}
