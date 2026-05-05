<?php

namespace Database\Seeders;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\Reservation;
use App\Services\Reservation\ReservationService;
use Illuminate\Database\Seeder;

class ReservationFlowSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $products = Product::query()
            ->whereIn('sku', [
                'FS-LAMP-001',
                'FS-STAND-002',
                'FS-TRAY-003',
                'FS-PANEL-004',
                'FS-DOCK-005',
            ])
            ->get()
            ->keyBy('sku');

        /** @var ReservationService $reservationService */
        $reservationService = app(ReservationService::class);

        $activeReservation = $reservationService->create([
            'idempotency_key' => 'seed-active-reservation',
            'customer_email' => 'active.customer@forsit.test',
            'expires_at' => now()->addHours(2),
            'items' => [
                [
                    'product_id' => $products['FS-LAMP-001']->id,
                    'quantity' => 2,
                ],
                [
                    'product_id' => $products['FS-DOCK-005']->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $releasedReservation = $reservationService->create([
            'idempotency_key' => 'seed-released-reservation',
            'customer_email' => 'released.customer@forsit.test',
            'expires_at' => now()->addHour(),
            'items' => [
                [
                    'product_id' => $products['FS-STAND-002']->id,
                    'quantity' => 1,
                ],
                [
                    'product_id' => $products['FS-TRAY-003']->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $reservationService->release($releasedReservation);

        $expiredReservation = $reservationService->create([
            'idempotency_key' => 'seed-expired-reservation',
            'customer_email' => 'expired.customer@forsit.test',
            'expires_at' => now()->addMinutes(20),
            'items' => [
                [
                    'product_id' => $products['FS-PANEL-004']->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $expiredReservation->update([
            'status' => ReservationStatus::Expired,
            'expires_at' => now()->subHour(),
        ]);

        $this->seedManualEdgeCases($products->all());

        $activeReservation->refresh();
    }

    /**
     * @param  array<string, Product>  $products
     */
    protected function seedManualEdgeCases(array $products): void
    {
        $reservation = Reservation::firstOrCreate(
            ['idempotency_key' => 'seed-manual-mixed-reservation'],
            [
                'customer_email' => 'manual.customer@forsit.test',
                'status' => ReservationStatus::Active,
                'subtotal' => '0.00',
                'expires_at' => now()->addMinutes(45),
            ],
        );

        if ($reservation->items()->exists()) {
            return;
        }

        $items = [
            [
                'product' => $products['FS-LAMP-001'],
                'quantity' => 1,
            ],
            [
                'product' => $products['FS-STAND-002'],
                'quantity' => 2,
            ],
        ];

        $subtotal = 0.0;

        foreach ($items as $item) {
            $price = $item['product']->latestPrice;
            $quantity = $item['quantity'];
            $unitPrice = (float) $price->amount;
            $lineTotal = round($unitPrice * $quantity, 2);

            $reservation->items()->create([
                'product_id' => $item['product']->id,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
            ]);

            $subtotal += $lineTotal;
        }

        $reservation->update([
            'subtotal' => number_format($subtotal, 2, '.', ''),
        ]);
    }
}
