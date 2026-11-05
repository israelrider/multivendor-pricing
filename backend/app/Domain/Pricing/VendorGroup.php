<?php

namespace App\Domain\Pricing;

/**
 * The lines one vendor supplies: what becomes a sub_orders row. Totals are sums of line totals.
 */
final readonly class VendorGroup
{
    public int $subtotalCents;

    public int $discountCents;

    public int $totalCents;

    /**
     * @param  list<QuotedLine>  $lines
     */
    public function __construct(
        public int $vendorId,
        public string $vendorName,
        public array $lines,
    ) {
        $this->subtotalCents = array_sum(array_map(fn (QuotedLine $l) => $l->line->subtotalCents, $lines));
        $this->discountCents = array_sum(array_map(fn (QuotedLine $l) => $l->discountCents, $lines));
        $this->totalCents = array_sum(array_map(fn (QuotedLine $l) => $l->totalCents, $lines));
    }
}
