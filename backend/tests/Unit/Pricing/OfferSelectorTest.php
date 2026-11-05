<?php

namespace Tests\Unit\Pricing;

use App\Models\Product;
use App\Models\ProductOffer;
use App\Domain\Pricing\OfferSelector;
use App\Domain\Pricing\UnpriceableProductException;
use PHPUnit\Framework\TestCase;

class OfferSelectorTest extends TestCase
{
    /**
     * @param  list<array{int, int, bool}>  $offers  [vendor_id, price_cents, is_active]
     */
    private function product(array $offers): Product
    {
        $product = (new Product)->forceFill(['id' => 7, 'name' => 'Skillet']);

        return $product->setRelation('offers', $product->newCollection(array_map(
            fn (array $o) => new ProductOffer(['vendor_id' => $o[0], 'price_cents' => $o[1], 'is_active' => $o[2]]),
            $offers,
        )));
    }

    public function test_it_picks_the_lowest_price(): void
    {
        $offer = (new OfferSelector)->select($this->product([[1, 2499, true], [2, 2299, true], [3, 2399, true]]));

        $this->assertSame(2, $offer->vendor_id);
        $this->assertSame(2299, $offer->price_cents);
    }

    public function test_a_price_tie_goes_to_the_lowest_vendor_id(): void
    {
        $offer = (new OfferSelector)->select($this->product([[3, 3999, true], [1, 3999, true], [2, 4999, true]]));

        $this->assertSame(1, $offer->vendor_id);
    }

    public function test_inactive_offers_are_ignored_even_when_cheaper(): void
    {
        $offer = (new OfferSelector)->select($this->product([[1, 2999, false], [3, 3499, true]]));

        $this->assertSame(3, $offer->vendor_id);
    }

    public function test_it_fails_when_no_offer_is_active(): void
    {
        try {
            (new OfferSelector)->select($this->product([[1, 2999, false]]));
            $this->fail('Expected UnpriceableProductException.');
        } catch (UnpriceableProductException $e) {
            $this->assertSame(7, $e->productId);
            $this->assertStringContainsString('Skillet', $e->getMessage());
        }
    }

    public function test_it_fails_when_there_are_no_offers(): void
    {
        $this->expectException(UnpriceableProductException::class);

        (new OfferSelector)->select($this->product([]));
    }
}
