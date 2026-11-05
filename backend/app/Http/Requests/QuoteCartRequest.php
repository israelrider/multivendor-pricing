<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A cart as the client sends it: a customer and product quantities. Never prices.
 *
 * Unknown, unavailable (no active offer) and duplicate products are rejected here, so
 * PricingService only ever sees a cart it can price. Its own checks stay as a last line of defence.
 */
class QuoteCartRequest extends FormRequest
{
    public const int MAX_ITEMS = 50;

    public const int MAX_QUANTITY = 999;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QUANTITY],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.*.product_id.distinct' => 'Each product may appear only once in a cart.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [$this->checkProductsArePurchasable(...)];
    }

    /**
     * One query for the whole cart instead of an `exists` rule per line.
     * Runs only when the item shapes are valid, so the ids are known to be integers.
     */
    private function checkProductsArePurchasable(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['items', 'items.*'])) {
            return;
        }

        $items = $this->input('items');

        $products = Product::query()
            ->withExists(['offers as has_active_offer' => fn ($query) => $query->active()])
            ->findMany(array_column($items, 'product_id'))
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $product = $products->get((int) $item['product_id']);

            if ($product === null) {
                $validator->errors()->add("items.{$index}.product_id", 'This product does not exist.');
            } elseif (! $product->has_active_offer) {
                $validator->errors()->add(
                    "items.{$index}.product_id",
                    "\"{$product->name}\" is not available from any vendor right now.",
                );
            }
        }
    }
}
