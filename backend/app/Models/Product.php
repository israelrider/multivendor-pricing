<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A product has no price of its own. Each vendor selling it has a ProductOffer.
 * Choosing which offer to use belongs to the OfferSelector, not to the model.
 */
#[Fillable(['category_id', 'name', 'sku'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<ProductOffer, $this> */
    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }
}
