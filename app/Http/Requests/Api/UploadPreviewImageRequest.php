<?php

namespace App\Http\Requests\Api;

use App\Models\CustomizedPreview;
use Illuminate\Foundation\Http\FormRequest;

class UploadPreviewImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'unique_code' => ['nullable', 'string', 'regex:'.CustomizedPreview::CODE_PATTERN],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.mimes' => 'Format gambar harus JPG, PNG, WebP, atau SVG.',
            'image.max' => 'Ukuran gambar maksimal 2 MB.',
        ];
    }
}
