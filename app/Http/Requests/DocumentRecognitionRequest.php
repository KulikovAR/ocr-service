<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentRecognitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => 'array',
            'images.*' => 'string',
            'scan' => 'nullable|string',
            'document_type' => 'required|string|in:PASSPORT,DRIVER_LICENSE,ID_CARD',
            'callback_url' => 'required|url',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.in' => 'Document type must be one of: PASSPORT, DRIVER_LICENSE, ID_CARD',
            'callback_url.url' => 'Callback URL must be a valid URL',
            'images.*.string' => 'Images must be strings (base64 encoded)',
            'scan.string' => 'Scan must be a string (base64 encoded)',
        ];
    }
}

            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'document_type.in' => 'Document type must be one of: PASSPORT, DRIVER_LICENSE, ID_CARD',
            'callback_url.url' => 'Callback URL must be a valid URL',
            'images.*.string' => 'Images must be strings (base64 encoded)',
            'scan.string' => 'Scan must be a string (base64 encoded)',
        ];
    }
}
