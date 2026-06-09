<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'name'            => $this->name,
            'legal_name'      => $this->legal_name,
            'vat_number'      => $this->vat_number,
            'country'         => $this->country,
            'logo_url'        => $this->logo_url,
            'settings'        => $this->settings,
            'status'          => $this->status,
            'approval_status' => $this->approval_status,
            'version'         => $this->version,
            'created_at'      => $this->created_at,

            // Relations — only when loaded
            'users' => UserResource::collection(
                $this->whenLoaded('users')
            ),
            'apps'  => AppResource::collection(
                $this->whenLoaded('apps')
            ),
        ];
    }
}
