<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'description'         => $this->description,
            'scope'               => $this->scope,
            'company_id'          => $this->company_id,
            'default_permissions' => $this->default_permissions,
            'version'             => $this->version,
            'created_at'          => $this->created_at,
        ];
    }
}
