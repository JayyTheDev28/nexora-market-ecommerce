<?php

namespace App\Http\Requests\Api\Mobile;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class BuyerRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawContact = trim((string) $this->input('contact_number', ''));
        if (str_starts_with($rawContact, '+63')) {
            $rawContact = '0' . substr($rawContact, 3);
        } elseif (str_starts_with($rawContact, '63') && strlen($rawContact) === 12) {
            $rawContact = '0' . substr($rawContact, 2);
        } elseif (preg_match('/^9\d{9}$/', $rawContact)) {
            $rawContact = '0' . $rawContact;
        }

        $this->merge([
            'contact_number' => $rawContact,
            'province_code' => $this->input('province_code') ?? $this->input('province'),
            'municipality_code' => $this->input('municipality_code') ?? $this->input('municipality'),
            'barangay_code' => $this->input('barangay_code') ?? $this->input('barangay'),
        ]);
    }

    public function rules(): array
    {
        return [
            'first_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\pL\s]+$/u',
                function ($attribute, $value, $fail) {
                    $firstName = trim((string) $value);
                    $lastName = trim((string) $this->input('last_name', ''));
                    if ($firstName !== '' && $lastName !== '') {
                        if (strtolower($firstName) === strtolower($lastName)) {
                            $fail('First name and last name cannot be identical.');
                            return;
                        }
                        $exists = User::whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower($firstName)])
                            ->whereRaw('LOWER(TRIM(last_name)) = ?', [strtolower($lastName)])
                            ->exists();
                        if ($exists) {
                            $fail('A user with this full name is already registered.');
                        }
                    }
                },
            ],
            'last_name' => [
                'required',
                'string',
                'min:2',
                'max:50',
                'regex:/^[\pL\s]+$/u',
            ],
            'middle_initial' => ['nullable', 'string', 'max:5', 'regex:/^[\pL\s]+$/u'],
            'sex' => ['nullable', 'in:male,female,other'],
            'birthday' => [
                'required',
                'date',
                'before_or_equal:2025-12-31',
                function ($attribute, $value, $fail) {
                    try {
                        $date = Carbon::parse($value);
                        if ($date->year >= 2026) {
                            $fail('Birthday cannot be in the year 2026 or later.');
                            return;
                        }
                        if ($date->age <= 0) {
                            $fail('Age cannot be 0. Users must be at least 1 year old.');
                        }
                    } catch (\Exception $e) {
                        $fail('Please provide a valid birth date.');
                    }
                },
            ],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => [
                'required',
                'string',
                'regex:/^09\d{9}$/',
                'unique:users,contact_number',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'province_code' => ['nullable', 'string'],
            'province_name' => ['nullable', 'string'],
            'municipality_code' => ['nullable', 'string'],
            'municipality_name' => ['nullable', 'string'],
            'barangay_code' => ['nullable', 'string'],
            'barangay_name' => ['nullable', 'string'],
            'street' => ['nullable', 'string', 'max:255'],
            'house_number' => ['nullable', 'string', 'max:255'],
            'valid_id' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name must contain letters and spaces only, with no special characters.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'first_name.max' => 'First name may not be greater than 50 characters.',
            'last_name.regex' => 'Last name must contain letters and spaces only, with no special characters.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'last_name.max' => 'Last name may not be greater than 50 characters.',
            'middle_initial.regex' => 'Middle initial may only contain letters.',
            'birthday.before_or_equal' => 'Birthday cannot be in the year 2026 or later.',
            'contact_number.regex' => 'Contact number must be an 11-digit Philippine mobile number starting with 09 (e.g. 09171234567) with no special characters.',
            'contact_number.unique' => 'This contact number has already been registered.',
            'password.min' => 'Password must be at least 8 characters and include uppercase, lowercase, numbers, and special characters.',
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

