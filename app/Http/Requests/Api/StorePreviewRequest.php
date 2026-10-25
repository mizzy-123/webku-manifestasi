<?php

namespace App\Http\Requests\Api;

use App\Models\CustomizedPreview;
use App\Models\Template;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreviewRequest extends FormRequest
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
            'template' => ['required', 'string', Rule::exists('templates', 'slug')->where('is_active', true)],
            'unique_code' => ['nullable', 'string', 'regex:'.CustomizedPreview::CODE_PATTERN],
            'custom_data' => ['required', 'array'],
        ];
    }

    /**
     * Resolve the template the preview belongs to.
     */
    public function template(): Template
    {
        return Template::query()->where('slug', $this->validated('template'))->firstOrFail();
    }
}
