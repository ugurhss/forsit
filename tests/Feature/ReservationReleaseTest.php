<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_reservation_is_released(): void
    {
        $reservation = $this->createActiveReservation('release-001');

        $response = $this->postJson("/api/reservations/{$reservation->id}/release");

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'released');
    }

    public function test_same_reservation_cannot_be_released_twice(): void
    {
        $reservation = $this->createActiveReservation('release-002');

        $this->postJson("/api/reservations/{$reservation->id}/release")->assertOk();

        $response = $this->postJson("/api/reservations/{$reservation->id}/release");

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.reservation.0', 'Only active reservations can be released.');
    }

    public function test_expired_reservation_cannot_be_released(): void
    {
        $reservation = $this->createActiveReservation('release-003');

        $reservation->update([
            'status' => ReservationStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/release");

        $response
            ->assertStatus(422)
            ->assertJsonPath('errors.reservation.0', 'Only active reservations can be released.');
    }

    protected function createActiveReservation(string $idempotencyKey): Reservation
    {
        $product = $this->createProduct(
            "Product {$idempotencyKey}",
            strtoupper($idempotencyKey),
            true,
            5,
            '120.00',
        );

        /** @var ReservationService $reservationService */
        $reservationService = app(ReservationService::class);

        return $reservationService->create([
            'idempotency_key' => $idempotencyKey,
            'customer_email' => 'release@gmail.com',
            'expires_at' => now()->addHour(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                ],
            ],
        ]);
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
