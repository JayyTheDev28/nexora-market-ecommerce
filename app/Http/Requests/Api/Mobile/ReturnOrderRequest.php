<?php

namespace App\Http\Requests\Api\Mobile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReturnOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['required', 'string', 'min:10', 'max:1500'],
            'media' => ['required', 'array', 'min:1', 'max:5'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'media.required' => 'Please provide at least 1 photo or video proof for your return request.',
            'media.min' => 'You must upload at least 1 photo or video evidence.',
            'media.max' => 'You may upload a maximum of 5 evidence files.',
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

