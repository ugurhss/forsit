<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_listed(): void
    {
        $firstProduct = $this->createProduct('Forsit Lamp', 'FS-LAMP-001', true, 5, '100.00');
        $secondProduct = $this->createProduct('Forsit Dock', 'FS-DOCK-002', true, 10, '200.00');

        $response = $this->getJson('/api/products');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $firstProduct->id, 'sku' => $firstProduct->sku])
            ->assertJsonFragment(['id' => $secondProduct->id, 'sku' => $secondProduct->sku]);
    }

    public function test_search_works(): void
    {
        $this->createProduct('Alpha Lamp', 'FS-SEARCH-001', true, 4, '100.00');
        $this->createProduct('Beta Tray', 'FS-SEARCH-002', true, 4, '120.00');

        $response = $this->getJson('/api/products?search=Lamp');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Alpha Lamp');
    }

    public function test_is_active_filter_works(): void
    {
        $activeProduct = $this->createProduct('Active Product', 'FS-ACTIVE-001', true, 3, '150.00');
        $this->createProduct('Inactive Product', 'FS-INACTIVE-001', false, 3, '180.00');

        $response = $this->getJson('/api/products?is_active=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeProduct->id)
            ->assertJsonPath('data.0.is_active', true);
    }

    public function test_pagination_works(): void
    {
        foreach (range(1, 3) as $index) {
            $this->createProduct(
                "Product {$index}",
                sprintf('FS-PAGE-%03d', $index),
                true,
                5,
                (string) (100 + $index),
            );
        }

        $response = $this->getJson('/api/products?per_page=2&page=2');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.total', 3);
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
