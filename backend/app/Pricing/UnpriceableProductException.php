<?php

namespace App\Pricing;

use RuntimeException;

/**
 * A cart product that cannot be priced: it does not exist, or no vendor has an active offer for it.
 */
final class UnpriceableProductException extends RuntimeException
{
    private function __construct(string $message, public readonly int $productId)
    {
        parent::__construct($message);
    }

    public static function unknownProduct(int $productId): self
    {
        return new self("Product {$productId} does not exist.", $productId);
    }

    public static function noActiveOffer(int $productId, string $productName): self
    {
        return new self("\"{$productName}\" is not available from any vendor right now.", $productId);
    }
}
