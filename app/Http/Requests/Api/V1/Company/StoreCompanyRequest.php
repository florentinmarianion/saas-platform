<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'legal_name'  => ['nullable', 'string', 'max:255'],
            'vat_number'  => ['nullable', 'string', 'max:50'],
            'country'     => ['nullable', 'string', 'size:2'],
            'settings'    => ['nullable', 'array'],
        ];
    }
}
