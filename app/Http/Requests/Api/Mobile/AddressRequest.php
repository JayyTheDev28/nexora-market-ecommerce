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

    public function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('phone') && !$this->has('recipient_phone')) {
            $merge['recipient_phone'] = $this->input('phone');
        }
        if ($this->has('fullName') && !$this->has('recipient_name')) {
            $merge['recipient_name'] = $this->input('fullName');
        }
        if ($this->has('city') && !$this->has('municipality_name')) {
            $merge['municipality_name'] = $this->input('city');
        }
        if ($this->has('province') && !$this->has('province_name')) {
            $merge['province_name'] = $this->input('province');
        }
        if ($this->has('barangay') && !$this->has('barangay_name')) {
            $merge['barangay_name'] = $this->input('barangay');
        }
        if ($this->has('region') && !$this->has('region_name')) {
            $merge['region_name'] = $this->input('region');
        }

        if (empty($this->input('province_code'))) {
            $merge['province_code'] = '043400000';
        }
        if (empty($this->input('municipality_code'))) {
            $merge['municipality_code'] = '043400000';
        }
        if (empty($this->input('barangay_code'))) {
            $merge['barangay_code'] = '043400000';
        }

        if (!empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string'],
            'region_code' => ['nullable', 'string', 'max:50'],
            'region_name' => ['nullable', 'string', 'max:100'],
            'province_code' => ['nullable', 'string', 'max:50'],
            'province_name' => ['required', 'string', 'max:100'],
            'municipality_code' => ['nullable', 'string', 'max:50'],
            'municipality_name' => ['required', 'string', 'max:100'],
            'barangay_code' => ['nullable', 'string', 'max:50'],
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

