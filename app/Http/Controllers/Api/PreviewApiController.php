<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePreviewRequest;
use App\Http\Requests\Api\UpdatePreviewRequest;
use App\Http\Requests\Api\UploadPreviewImageRequest;
use App\Models\CustomizedPreview;
use App\Models\Setting;
use App\Services\CustomDataSanitizer;
use App\Services\PreviewImageService;
use Illuminate\Http\JsonResponse;

class PreviewApiController extends Controller
{
    public function __construct(
        private readonly CustomDataSanitizer $sanitizer,
        private readonly PreviewImageService $images,
    ) {}

    /**
     * First save: create a preview with a unique code (a code reserved by an earlier upload is reused).
     */
    public function store(StorePreviewRequest $request): JsonResponse
    {
        $code = $request->validated('unique_code');

        if ($code === null || CustomizedPreview::query()->where('unique_code', $code)->exists()) {
            $code = CustomizedPreview::generateUniqueCode();
        }

        $preview = CustomizedPreview::query()->create([
            'template_id' => $request->template()->id,
            'unique_code' => $code,
            'custom_data' => $this->sanitizer->sanitize($request->validated('custom_data')),
            'last_accessed_at' => now(),
            'expires_at' => now()->addDays(CustomizedPreview::RETENTION_DAYS),
        ]);

        return response()->json($this->payload($preview), 201);
    }

    /**
     * Later saves overwrite the data behind the same link.
     */
    public function update(UpdatePreviewRequest $request, string $uniqueCode): JsonResponse
    {
        $preview = $this->findAvailable($uniqueCode);

        $preview->custom_data = $this->sanitizer->sanitize($request->validated('custom_data'));
        $preview->touchRetention();

        return response()->json($this->payload($preview));
    }

    /**
     * Store a visitor upload; reserves a code when the visitor has not saved yet.
     */
    public function uploadImage(UploadPreviewImageRequest $request): JsonResponse
    {
        $code = $request->validated('unique_code') ?? CustomizedPreview::generateUniqueCode();

        return response()->json([
            'unique_code' => $code,
            'url' => $this->images->store($request->file('image'), $code),
        ], 201);
    }

    /**
     * Record the WhatsApp order click and return the prefilled WhatsApp URL.
     */
    public function orderClick(string $uniqueCode): JsonResponse
    {
        $preview = $this->findAvailable($uniqueCode);

        $preview->forceFill(['order_clicked_at' => now()])->save();

        $message = strtr(
            Setting::getValue('whatsapp_message_template', '{template_name} {preview_url}'),
            [
                '{template_name}' => $preview->template->name,
                '{preview_url}' => route('previews.show', $preview->unique_code),
            ],
        );

        return response()->json([
            'whatsapp_url' => 'https://wa.me/'.Setting::getValue('whatsapp_number').'?text='.rawurlencode($message),
        ]);
    }

    private function findAvailable(string $uniqueCode): CustomizedPreview
    {
        $preview = CustomizedPreview::query()->with('template')->where('unique_code', $uniqueCode)->firstOrFail();

        abort_unless($preview->isAvailable(), 404);

        return $preview;
    }

    /**
     * @return array{unique_code: string, url: string, expires_at: string}
     */
    private function payload(CustomizedPreview $preview): array
    {
        return [
            'unique_code' => $preview->unique_code,
            'url' => route('previews.show', $preview->unique_code),
            'expires_at' => $preview->expires_at->toIso8601String(),
        ];
    }
}
