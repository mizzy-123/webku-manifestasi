<?php

namespace App\Models;

use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property string $thumbnail_path
 * @property string $storage_path
 * @property array{
 *     name?: string,
 *     type?: string,
 *     pages?: list<array{slug: string, title: string, file: string}>,
 *     colors?: list<array{variable: string, label: string, default: string}>
 * } $manifest_data
 * @property string|null $price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category $category
 * @property-read Collection<int, CustomizedPreview> $customizedPreviews
 */
#[Fillable([
    'category_id',
    'name',
    'slug',
    'type',
    'description',
    'thumbnail_path',
    'storage_path',
    'manifest_data',
    'price',
    'is_active',
])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    public const TYPE_SINGLE_PAGE = 'single-page';

    public const TYPE_MULTI_PAGE = 'multi-page';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'manifest_data' => 'array',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<CustomizedPreview, $this>
     */
    public function customizedPreviews(): HasMany
    {
        return $this->hasMany(CustomizedPreview::class);
    }
}
