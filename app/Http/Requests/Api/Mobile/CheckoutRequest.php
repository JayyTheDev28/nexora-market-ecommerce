<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('shipping_address_id') && !$this->has('address_id')) {
            $this->merge(['address_id' => $this->input('shipping_address_id')]);
        }
    }

    public function rules(): array
    {
        return [
            'address_id' => ['nullable', 'integer'],
            'shipping_address_id' => ['nullable', 'integer'],
            'recipient_name' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'recipient_phone' => ['required_without:address_id', 'nullable', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'region_code' => ['nullable', 'string'],
            'region_name' => ['nullable', 'string'],
            'province_code' => ['required_without:address_id', 'nullable', 'string'],
            'province_name' => ['nullable', 'string'],
            'municipality_code' => ['required_without:address_id', 'nullable', 'string'],
            'municipality_name' => ['nullable', 'string'],
            'barangay_code' => ['required_without:address_id', 'nullable', 'string'],
            'barangay_name' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'payment_method' => ['nullable', 'string', 'in:cod'],
            'platform_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'shipping_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'store_voucher_id' => ['nullable', 'integer', 'exists:vouchers,id'],
            'idempotency_token' => ['nullable', 'string'],
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

