<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'whatsapp_number' => ['required', 'string', 'regex:/^[1-9][0-9]{7,14}$/'],
            'whatsapp_message_template' => ['required', 'string', 'max:1000'],
            'wp_theme_author' => ['required', 'string', 'max:100'],
            'wp_theme_author_uri' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp_number.regex' => 'Nomor WhatsApp harus berupa angka dengan kode negara tanpa tanda + (contoh: 6281234567890).',
        ];
    }
}
