<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'module'               => $this->module,
            'name'                 => $this->name,
            'description'          => $this->description,
            'icon'                 => $this->icon,
            'semver'               => $this->semver,
            'declared_permissions' => $this->declared_permissions,
            'status'               => $this->status,
            'approval_status'      => $this->approval_status,

            // Company activation context (only when loaded via BelongsToMany)
            'activation' => $this->when(
                $this->pivot !== null,
                fn() => [
                    'status'        => $this->pivot->status,
                    'trial_ends_at' => $this->pivot->trial_ends_at,
                    'config'        => $this->pivot->config,
                ]
            ),
        ];
    }
}
