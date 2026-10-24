<?php

namespace App\Models;

use Database\Factories\CustomizedPreviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
