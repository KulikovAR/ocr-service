<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessDocumentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'document_id' => 'required|string|max:255',
            'document_type' => 'required|string|in:PASSPORT,PASSPORT_REG,DLIC,SNILS,STS',
            'images' => 'required|array|min:1|max:10',
            'images.*' => 'required|string|min:10240',
            'callback_url' => 'nullable|url|max:500',
            'metadata' => 'nullable|array',
            'metadata.*' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'document_id.required' => 'Document ID is required',
            'document_id.string' => 'Document ID must be a string',
            'document_id.max' => 'Document ID cannot exceed 255 characters',
            'document_type.required' => 'Document type is required',
            'document_type.string' => 'Document type must be a string',
            'document_type.in' => 'Document type must be one of: PASSPORT, PASSPORT_REG, DLIC, SNILS, STS',
            'images.required' => 'At least one image is required',
            'images.array' => 'Images must be an array',
            'images.min' => 'At least one image is required',
            'images.max' => 'Maximum 10 images allowed',
            'images.*.required' => 'Each image is required',
            'images.*.string' => 'Each image must be a base64 string',
            'images.*.min' => 'Each image must be at least 10KB in size',
            'callback_url.url' => 'Callback URL must be a valid URL',
            'callback_url.max' => 'Callback URL cannot exceed 500 characters',
            'metadata.array' => 'Metadata must be an array',
            'metadata.*.string' => 'Metadata values must be strings',
            'metadata.*.max' => 'Metadata values cannot exceed 1000 characters',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'document_id' => 'идентификатор документа',
            'document_type' => 'тип документа',
            'images' => 'изображения',
            'callback_url' => 'callback URL',
        ];
    }

    public function withValidator($validator)
    {
        return;


        $validator->after(function ($validator) {
            $images = $this->input('images', []);

            foreach ($images as $index => $image) {
                if (!base64_decode($image, true)) {
                    $validator->errors()->add("images.{$index}", 'Invalid base64 format');
                    continue;
                }

                $decoded = base64_decode($image);

                if (strlen($decoded) < 10240) {
                    $validator->errors()->add("images.{$index}", 'File size must be at least 10 KB');
                    continue;
                }

                if (strlen($decoded) > 5242880) {
                    $validator->errors()->add("images.{$index}", 'File size must not exceed 5 MB');
                    continue;
                }

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_buffer($finfo, $decoded);
                finfo_close($finfo);

                if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/jpg'])) {
                    $validator->errors()->add("images.{$index}", 'File must be a valid image (JPEG, PNG)');
                    continue;
                }

                $imageInfo = getimagesizefromstring($decoded);
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];

                    if ($width < 1000 || $width > 5000 || $height < 1000 || $height > 5000) {
                        $validator->errors()->add("images.{$index}", 'Image dimensions must be between 1000x1000 and 5000x5000 pixels');
                    }
                }
            }
        });
    }
}
