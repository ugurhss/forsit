<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_request_returns_successfully(): void
    {
        $product = $this->createProduct('Forsit Lamp', 'FS-QUOTE-001', true, 5, '149.90');

        $response = $this->postJson('/api/cart/quote', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.unit_price', '149.90')
            ->assertJsonPath('data.subtotal', '299.80');
    }

    public function test_inactive_product_returns_error(): void
    {
        $product = $this->createProduct('Inactive Lamp', 'FS-QUOTE-002', false, 5, '120.00');

        $response = $this->postJson('/api/cart/quote', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.reason', 'Product is unavailable.');
    }

    public function test_insufficient_stock_returns_error(): void
    {
        $product = $this->createProduct('Low Stock Lamp', 'FS-QUOTE-003', true, 1, '250.00');

        $response = $this->postJson('/api/cart/quote', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.0.reason', 'Insufficient stock.');
    }

    public function test_price_manipulation_is_not_possible(): void
    {
        $product = $this->createProduct('Secured Price Product', 'FS-QUOTE-004', true, 3, '999.90');

        $response = $this->postJson('/api/cart/quote', [
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '1.00',
                    'line_total' => '1.00',
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.items.0.unit_price', '999.90')
            ->assertJsonPath('data.items.0.line_total', '999.90')
            ->assertJsonPath('data.subtotal', '999.90');
    }

    protected function createProduct(
        string $name,
        string $sku,
        bool $isActive,
        int $stockQuantity,
        string $priceAmount,
        string $currency = 'TRY',
    ): Product {
        $product = Product::factory()->create([
            'name' => $name,
            'sku' => $sku,
            'is_active' => $isActive,
        ]);

        ProductPrice::create([
            'product_id' => $product->id,
            'amount' => $priceAmount,
            'currency' => $currency,
        ]);

        ProductStock::create([
            'product_id' => $product->id,
            'quantity' => $stockQuantity,
        ]);

        return $product->fresh(['latestPrice', 'stock']);
    }
}
