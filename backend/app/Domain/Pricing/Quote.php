<?php

namespace App\Domain\Pricing;

/**
 * A priced cart. Lines keep the cart order; vendor groups are ordered by vendor id.
 * Order totals are sums of group totals, which are sums of line totals, so they match to the cent.
 */
final readonly class Quote
{
    /** @var list<VendorGroup> */
    public array $vendorGroups;

    public int $subtotalCents;

    public int $discountCents;

    public int $totalCents;

    /**
     * @param  list<QuotedLine>  $lines
     */
    public function __construct(
        public PricingContext $context,
        public array $lines,
    ) {
        $byVendor = [];
        foreach ($lines as $line) {
            $byVendor[$line->line->vendorId][] = $line;
        }
        ksort($byVendor);

        $this->vendorGroups = array_values(array_map(
            fn (array $group) => new VendorGroup($group[0]->line->vendorId, $group[0]->line->vendorName, $group),
            $byVendor,
        ));

        $this->subtotalCents = array_sum(array_map(fn (VendorGroup $g) => $g->subtotalCents, $this->vendorGroups));
        $this->discountCents = array_sum(array_map(fn (VendorGroup $g) => $g->discountCents, $this->vendorGroups));
        $this->totalCents = array_sum(array_map(fn (VendorGroup $g) => $g->totalCents, $this->vendorGroups));
    }
}
