<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'email'    => $this->email,
            'locale'   => $this->locale,
            'timezone' => $this->timezone,
            'status'   => $this->status,

            // Only include companies when loaded
            'companies' => CompanyBriefResource::collection(
                $this->whenLoaded('companies')
            ),
        ];
    }
}
