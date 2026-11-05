<?php

namespace App\Domain\Orders;

use RuntimeException;

/**
 * An Idempotency-Key that already belongs to an order was sent with a different customer or cart.
 * Replaying the old order would be wrong and placing a new one would break the key's promise, so
 * the request is refused (422).
 */
final class IdempotencyKeyReusedException extends RuntimeException
{
    public function __construct(public readonly string $idempotencyKey)
    {
        parent::__construct('This Idempotency-Key was already used for a different order. Send a new key for a new order.');
    }
}
