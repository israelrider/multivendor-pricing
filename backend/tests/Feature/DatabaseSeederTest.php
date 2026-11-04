<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_it_seeds_the_demo_catalog_and_customers(): void
    {
        $this->assertSame(3, Vendor::count());
        $this->assertSame(4, Category::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(3, Customer::count());
        $this->assertSame(1, Customer::where('is_loyal', true)->count());
    }

    public function test_every_product_has_at_least_one_active_offer(): void
    {
        $this->assertSame(0, Product::whereDoesntHave('offers', fn ($q) => $q->active())->count());
    }

    public function test_most_products_have_offers_from_several_vendors(): void
    {
        $this->assertGreaterThanOrEqual(6, Product::has('offers', '>=', 2)->count());
    }

    public function test_it_contains_a_price_tie_between_vendors(): void
    {
        $prices = ProductOffer::active()
            ->whereRelation('product', 'sku', 'ELEC-002')
            ->orderBy('vendor_id')
            ->get();

        $this->assertCount(2, $prices);
        $this->assertSame($prices[0]->price_cents, $prices[1]->price_cents);
    }

    public function test_it_contains_a_cheaper_inactive_offer(): void
    {
        $offers = ProductOffer::whereRelation('product', 'sku', 'HOME-003')->get();

        $inactive = $offers->firstWhere('is_active', false);
        $cheapestActive = $offers->where('is_active', true)->min('price_cents');

        $this->assertNotNull($inactive);
        $this->assertLessThan($cheapestActive, $inactive->price_cents);
    }

    public function test_reseeding_does_not_duplicate_rows(): void
    {
        $this->seed();

        $this->assertSame(3, Vendor::count());
        $this->assertSame(10, Product::count());
        $this->assertSame(3, Customer::count());
        $this->assertSame(20, ProductOffer::count());
    }
}
