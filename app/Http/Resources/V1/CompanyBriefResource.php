<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyBriefResource extends JsonResource
{
    /**
     * Lightweight company representation used in user responses.
     * Includes membership context from pivot — without exposing raw pivot data.
     */
    public function toArray(Request $request): array
    {
        return [
            'id'   => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,

            // Membership context (only when loaded via BelongsToMany)
            'membership' => $this->when(
                $this->pivot !== null,
                fn() => [
                    'role_label'      => $this->pivot->role_label,
                    'is_owner'        => (bool) $this->pivot->is_owner,
                    'status'          => $this->pivot->status,
                    'approval_status' => $this->pivot->approval_status,
                    'joined_at'       => $this->pivot->joined_at,
                ]
            ),
        ];
    }
}
