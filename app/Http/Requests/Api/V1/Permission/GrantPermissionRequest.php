<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Permission;

use Illuminate\Foundation\Http\FormRequest;

class GrantPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'permission'  => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]+[a-z0-9]$/'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'permission.regex' => 'Permission must follow pattern: resource.action (e.g. invoices.approve)',
        ];
    }
}
