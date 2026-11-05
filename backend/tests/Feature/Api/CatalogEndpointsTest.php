<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductOffer;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = DatabaseSeeder::class;

    /** @return array<string, mixed> */
    private function productBySku(string $sku): array
    {
        return collect($this->getJson('/api/products')->assertOk()->json('data'))->firstWhere('sku', $sku);
    }

    public function test_products_list_every_product_with_category_and_offers(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure(['data' => [[
                'id', 'name', 'sku',
                'category' => ['id', 'name', 'slug'],
                'offers' => [['id', 'vendor' => ['id', 'name'], 'price_cents']],
                'best_offer' => ['id', 'vendor' => ['id', 'name'], 'price_cents'],
            ]]]);
    }

    public function test_offers_are_sorted_cheapest_first_and_best_offer_matches_the_cart_choice(): void
    {
        $mouse = $this->productBySku('ELEC-001');

        $this->assertSame([2_299, 2_399, 2_499], array_column($mouse['offers'], 'price_cents'));
        $this->assertSame('Brightline Traders', $mouse['best_offer']['vendor']['name']);
    }

    public function test_a_price_tie_goes_to_the_lowest_vendor_id(): void
    {
        $this->assertSame('Acme Supply', $this->productBySku('ELEC-002')['best_offer']['vendor']['name']);
    }

    public function test_inactive_offers_are_not_listed(): void
    {
        $skillet = $this->productBySku('HOME-003');

        $this->assertCount(1, $skillet['offers']);
        $this->assertSame('Corner Market', $skillet['best_offer']['vendor']['name']);
    }

    public function test_a_product_nobody_sells_has_no_best_offer(): void
    {
        $product = Product::factory()->create(['sku' => 'GONE-001']);
        ProductOffer::factory()->for($product)->inactive()->create();

        $gone = $this->productBySku('GONE-001');

        $this->assertSame([], $gone['offers']);
        $this->assertNull($gone['best_offer']);
    }

    public function test_customers_are_listed_with_their_loyalty_flag(): void
    {
        $response = $this->getJson('/api/customers')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'is_loyal']]]);

        $alex = collect($response->json('data'))->firstWhere('email', 'alex@example.com');
        $this->assertTrue($alex['is_loyal']);
    }
}
