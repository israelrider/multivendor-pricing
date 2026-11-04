<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * One cart line as the client sends it. There is no price: the server always prices the cart itself.
 */
final readonly class CartItem
{
    public function __construct(
        public int $productId,
        public int $quantity,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException("Quantity must be at least 1, got {$quantity}.");
        }
    }

    /**
     * @param  iterable<array{product_id: int|string, quantity: int|string}>  $items  validated request input
     * @return list<self>
     */
    public static function listFrom(iterable $items): array
    {
        $list = [];

        foreach ($items as $item) {
            $list[] = new self((int) $item['product_id'], (int) $item['quantity']);
        }

        return $list;
    }
}
