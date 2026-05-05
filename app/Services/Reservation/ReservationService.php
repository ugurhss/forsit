<?php

namespace App\Services\Reservation;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Services\Cart\CartService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(
        protected CartService $cartService,
    ) {}

    public function create(array $payload): Reservation
    {
        $idempotencyKey = trim((string) ($payload['idempotency_key'] ?? ''));
        $customerEmail = trim((string) ($payload['customer_email'] ?? ''));
        $expiresAt = $payload['expires_at'] ?? now()->addMinutes(15);
        $items = $payload['items'] ?? [];

        if ($idempotencyKey === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => ['The idempotency_key field is required.'],
            ]);
        }

        if ($customerEmail === '') {
            throw ValidationException::withMessages([
                'customer_email' => ['The customer_email field is required.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($customerEmail, $expiresAt, $idempotencyKey, $items): Reservation {
                $existingReservation = Reservation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existingReservation) {
                    return $existingReservation->load(['items.product.latestPrice', 'items.product.stock']);
                }

                $quote = $this->cartService->assertReservable($items);
                $normalizedItems = collect($quote['data']['items'])->keyBy('product_id');
                $productIds = $normalizedItems->keys()->all();

                $stocks = ProductStock::query()
                    ->whereIn('product_id', $productIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id');
                $reservedQuantities = $this->getReservedQuantities($productIds);

                $products = Product::query()
                    ->with('latestPrice')
                    ->whereIn('id', $productIds)
                    ->where('is_active', true)
                    ->get()
                    ->keyBy('id');

                $reservation = Reservation::create([
                    'idempotency_key' => $idempotencyKey,
                    'customer_email' => $customerEmail,
                    'status' => ReservationStatus::Active,
                    'subtotal' => '0.00',
                    'expires_at' => $expiresAt,
                ]);

                $subtotal = 0.0;

                foreach ($normalizedItems as $productId => $line) {
                    $product = $products->get($productId);
                    $stock = $stocks->get($productId);
                    $requestedQuantity = (int) $line['quantity'];
                    $physicalQuantity = (int) ($stock?->quantity ?? 0);
                    $reservedQuantity = (int) ($reservedQuantities[$productId] ?? 0);
                    $availableQuantity = max(0, $physicalQuantity - $reservedQuantity);

                    if (! $product || ! $product->latestPrice) {
                        throw ValidationException::withMessages([
                            'items' => ["Product {$productId} is unavailable."],
                        ]);
                    }

                    if ($availableQuantity < $requestedQuantity) {
                        throw ValidationException::withMessages([
                            'items' => ["Product {$product->sku} does not have enough stock."],
                        ]);
                    }

                    $unitPrice = (float) $product->latestPrice->amount;
                    $lineTotal = round($unitPrice * $requestedQuantity, 2);

                    $reservation->items()->create([
                        'product_id' => $product->id,
                        'quantity' => $requestedQuantity,
                        'unit_price' => $this->formatDecimal($unitPrice),
                        'line_total' => $this->formatDecimal($lineTotal),
                    ]);
                    $subtotal += $lineTotal;
                }

                $reservation->update([
                    'subtotal' => $this->formatDecimal($subtotal),
                ]);

                return $reservation->load(['items.product.latestPrice', 'items.product.stock']);
            }, 3);
        } catch (QueryException $exception) {
            if ($this->isDuplicateIdempotencyKey($exception)) {
                return Reservation::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail()
                    ->load(['items.product.latestPrice', 'items.product.stock']);
            }

            throw $exception;
        }
    }

    public function release(int|Reservation $reservation): Reservation
    {
        $reservationId = $reservation instanceof Reservation ? $reservation->id : $reservation;

        return DB::transaction(function () use ($reservationId): Reservation {
            $lockedReservation = Reservation::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($reservationId);

            if ($lockedReservation->status !== ReservationStatus::Active) {
                throw ValidationException::withMessages([
                    'reservation' => ['Only active reservations can be released.'],
                ]);
            }

            $lockedReservation->update([
                'status' => ReservationStatus::Released,
            ]);

            return $lockedReservation->load(['items.product.latestPrice', 'items.product.stock']);
        }, 3);
    }

    protected function isDuplicateIdempotencyKey(QueryException $exception): bool
    {
        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    protected function getReservedQuantities(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return ReservationItem::query()
            ->selectRaw('reservation_items.product_id, SUM(reservation_items.quantity) as reserved_quantity')
            ->join('reservations', 'reservations.id', '=', 'reservation_items.reservation_id')
            ->whereIn('reservation_items.product_id', $productIds)
            ->where('reservations.status', ReservationStatus::Active)
            ->where('reservations.expires_at', '>', now())
            ->groupBy('reservation_items.product_id')
            ->lockForUpdate()
            ->pluck('reserved_quantity', 'product_id');
    }

    protected function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
