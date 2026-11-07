<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

/**
 * Fixed demo catalog: 3 vendors, 4 categories, 10 products.
 *
 * The data is chosen to exercise the pricing rules, not random:
 *   - most products have overlapping offers, so the cheapest vendor differs per product;
 *   - ELEC-002 is a price tie between Acme and Corner, so the lowest vendor id (Acme) must win;
 *   - HOME-003 has a cheaper offer that is inactive, which must be ignored;
 *   - some products have a single vendor.
 *
 * Re-running is safe: rows are matched on their natural keys (email, slug, sku, product+vendor).
 */
class CatalogSeeder extends Seeder
{
    private const array VENDORS = [
        'acme' => ['name' => 'Acme Supply', 'email' => 'orders@acme.example.com'],
        'brightline' => ['name' => 'Brightline Traders', 'email' => 'sales@brightline.example.com'],
        'corner' => ['name' => 'Corner Market', 'email' => 'hello@corner.example.com'],
    ];

    private const array CATEGORIES = [
        'electronics' => 'Electronics',
        'books' => 'Books',
        'home-kitchen' => 'Home & Kitchen',
        'toys' => 'Toys',
    ];

    /**
     * sku => [category slug, name, [vendor => [price_cents, is_active]]]
     */
    private const array PRODUCTS = [
        'ELEC-001' => ['electronics', 'Wireless Mouse', ['acme' => [2499, true], 'brightline' => [2299, true], 'corner' => [2399, true]]],
        'ELEC-002' => ['electronics', 'USB-C Charger 65W', ['acme' => [3999, true], 'corner' => [3999, true]]],
        'ELEC-003' => ['electronics', 'Noise-Cancelling Headphones', ['brightline' => [12999, true], 'corner' => [11999, true]]],
        'BOOK-001' => ['books', 'Clean Code', ['acme' => [3499, true]]],
        'BOOK-002' => ['books', 'The Pragmatic Programmer', ['acme' => [4199, true], 'brightline' => [3899, true]]],
        'HOME-001' => ['home-kitchen', 'Chef Knife 8"', ['brightline' => [5999, true], 'corner' => [6499, true]]],
        'HOME-002' => ['home-kitchen', 'French Press', ['acme' => [2799, true], 'corner' => [2599, true]]],
        'HOME-003' => ['home-kitchen', 'Cast Iron Skillet', ['acme' => [2999, false], 'corner' => [3499, true]]],
        'TOY-001' => ['toys', 'Building Blocks Set', ['acme' => [4999, true], 'brightline' => [5299, true], 'corner' => [4799, true]]],
        'TOY-002' => ['toys', 'Wooden Puzzle', ['brightline' => [1599, true]]],
    ];

    public function run(): void
    {
        // Created in this order so vendor ids follow it (acme < brightline < corner),
        // which the ELEC-002 tie-break relies on.
        $vendors = collect(self::VENDORS)->map(
            fn (array $v) => Vendor::updateOrCreate(['email' => $v['email']], ['name' => $v['name']]),
        );

        $categories = collect(self::CATEGORIES)->map(
            fn (string $name, string $slug) => Category::updateOrCreate(['slug' => $slug], ['name' => $name]),
        );

        foreach (self::PRODUCTS as $sku => [$categorySlug, $name, $offers]) {
            $product = Product::updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'category_id' => $categories[$categorySlug]->id],
            );

            foreach ($offers as $vendorKey => [$priceCents, $isActive]) {
                ProductOffer::updateOrCreate(
                    ['product_id' => $product->id, 'vendor_id' => $vendors[$vendorKey]->id],
                    ['price_cents' => $priceCents, 'is_active' => $isActive],
                );
            }
        }
    }
}
