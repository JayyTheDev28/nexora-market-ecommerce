<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class AddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region_code' => ['nullable', 'string', 'max:50'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'province_code' => ['required', 'string', 'max:50'],
            'province_name' => ['required', 'string', 'max:100'],
            'municipality_code' => ['required', 'string', 'max:50'],
            'municipality_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['required', 'string', 'max:50'],
            'barangay_name' => ['required', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}

