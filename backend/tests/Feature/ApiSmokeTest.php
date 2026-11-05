<?php

namespace Tests\Feature;

use App\Domain\Pricing\UnpriceableProductException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    public function test_api_routes_are_mounted_under_the_api_prefix(): void
    {
        $this->getJson('/api/ping')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_unknown_api_routes_return_json_404(): void
    {
        $this->getJson('/api/does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_unpriceable_product_returns_json_422(): void
    {
        Route::post('/api/test-unpriceable', fn () => throw UnpriceableProductException::unknownProduct(999));

        $this->postJson('/api/test-unpriceable')
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'Product 999 does not exist.',
                'product_id' => 999,
            ]);
    }

    public function test_health_check_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
