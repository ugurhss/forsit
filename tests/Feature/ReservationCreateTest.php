<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_is_created_successfully(): void
    {
        $product = $this->createProduct('Forsit Lamp', 'FS-RES-001', true, 5, '199.90');

        $response = $this->postJson('/api/reservations', [
            'idempotency_key' => 'res-create-001',
            'customer_email' => 'customer@gmail.com',
            'expires_at' => now()->addHour()->toISOString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.subtotal', '399.80');

        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('reservation_items', 1);
    }

    public function test_second_request_with_same_idempotency_key_returns_same_reservation(): void
    {
        $product = $this->createProduct('Forsit Dock', 'FS-RES-002', true, 5, '99.90');

        $payload = [
            'idempotency_key' => 'same-key-001',
            'customer_email' => 'repeat@gmail.com',
            'expires_at' => now()->addHour()->toISOString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $firstResponse = $this->postJson('/api/reservations', $payload);
        $secondResponse = $this->postJson('/api/reservations', $payload);

        $firstResponse->assertCreated();
        $secondResponse->assertCreated();

        $this->assertSame(
            $firstResponse->json('data.id'),
            $secondResponse->json('data.id'),
        );
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_insufficient_stock_returns_error(): void
    {
        $product = $this->createProduct('Low Stock Product', 'FS-RES-003', true, 1, '89.90');

        $response = $this->postJson('/api/reservations', [
            'idempotency_key' => 'insufficient-stock-001',
            'customer_email' => 'stock@gmail.com',
            'expires_at' => now()->addHour()->toISOString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.items.0', 'Insufficient stock.');
    }

    public function test_two_competing_requests_do_not_push_stock_below_zero(): void
    {
        $product = $this->createProduct('Single Unit Product', 'FS-RES-004', true, 1, '150.00');

        $firstResponse = $this->postJson('/api/reservations', [
            'idempotency_key' => 'compete-001',
            'customer_email' => 'first@gmail.com',
            'expires_at' => now()->addHour()->toISOString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $secondResponse = $this->postJson('/api/reservations', [
            'idempotency_key' => 'compete-002',
            'customer_email' => 'second@gmail.com',
            'expires_at' => now()->addHour()->toISOString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $firstResponse->assertCreated();
        $secondResponse->assertStatus(422);

        $activeReservedQuantity = Reservation::query()
            ->where('status', 'active')
            ->withSum('items', 'quantity')
            ->get()
            ->sum('items_sum_quantity');

        $this->assertSame(1, (int) $activeReservedQuantity);
        $this->assertDatabaseCount('reservations', 1);
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
