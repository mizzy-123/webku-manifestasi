<?php

namespace App\Models;

use Database\Factories\CustomizedPreviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $template_id
 * @property string $unique_code
 * @property array{
 *     colors?: array<string, string>,
 *     global?: array{texts?: array<string, string>, images?: array<string, string>, links?: array<string, array{text?: string, href?: string}>},
 *     pages?: array<string, array{texts?: array<string, string>, images?: array<string, string>, links?: array<string, array{text?: string, href?: string}>}>
 * } $custom_data
 * @property Carbon|null $order_clicked_at
 * @property Carbon|null $exported_at
 * @property bool $is_locked_by_admin
 * @property Carbon $last_accessed_at
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Template $template
 */
#[Fillable([
    'template_id',
    'unique_code',
    'custom_data',
    'order_clicked_at',
    'exported_at',
    'is_locked_by_admin',
    'last_accessed_at',
    'expires_at',
])]
class CustomizedPreview extends Model
{
    /** @use HasFactory<CustomizedPreviewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'custom_data' => 'array',
            'order_clicked_at' => 'datetime',
            'exported_at' => 'datetime',
            'is_locked_by_admin' => 'boolean',
            'last_accessed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public const RETENTION_DAYS = 30;

    public const CODE_PATTERN = '/^[A-Za-z0-9]{10}$/';

    /**
     * Generate a random 10 character code that is not used by any preview yet.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::random(10);
        } while (static::query()->where('unique_code', $code)->exists());

        return $code;
    }

    /**
     * A preview is available when an admin locked it or it has not passed its expiry date.
     */
    public function isAvailable(): bool
    {
        return $this->is_locked_by_admin || $this->expires_at->isFuture();
    }

    /**
     * Record a visit or save: bump last access and extend the 30 day retention window.
     */
    public function touchRetention(): void
    {
        $this->forceFill([
            'last_accessed_at' => now(),
            'expires_at' => now()->addDays(self::RETENTION_DAYS),
        ])->save();
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
