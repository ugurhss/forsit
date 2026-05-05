<?php

namespace App\Services\Cart;

use App\Enums\ReservationStatus;
use App\Models\Product;
use App\Models\ReservationItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function quote(array $items): array
    {
        $normalizedItems = $this->normalizeItems($items);
        $products = Product::query()
            ->with(['latestPrice', 'stock'])
            ->whereIn('id', $normalizedItems->keys())
            ->get()
            ->keyBy('id');
        $availableQuantities = $this->getAvailableQuantities($normalizedItems->keys()->all());

        $lines = [];
        $issues = [];
        $subtotal = 0.0;
        $currencies = [];

        foreach ($normalizedItems as $productId => $quantity) {
            $product = $products->get($productId);

            if (! $product || ! $product->is_active) {
                $issues[] = [
                    'product_id' => $productId,
                    'reason' => 'Product is unavailable.',
                ];

                continue;
            }

            if (! $product->latestPrice) {
                $issues[] = [
                    'product_id' => $productId,
                    'reason' => 'Product price is missing.',
                ];

                continue;
            }

            $availableQuantity = (int) ($availableQuantities[$productId] ?? 0);
            $unitPrice = (float) $product->latestPrice->amount;
            $lineTotal = round($unitPrice * $quantity, 2);
            $hasSufficientStock = $availableQuantity >= $quantity;
            $currency = $product->latestPrice->currency;

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $quantity,
                'available_quantity' => $availableQuantity,
                'unit_price' => $this->formatDecimal($unitPrice),
                'line_total' => $this->formatDecimal($lineTotal),
                'currency' => $currency,
                'in_stock' => $hasSufficientStock,
            ];

            $currencies[$currency] = true;

            if (! $hasSufficientStock) {
                $issues[] = [
                    'product_id' => $product->id,
                    'reason' => 'Insufficient stock.',
                    'requested_quantity' => $quantity,
                    'available_quantity' => $availableQuantity,
                ];

                continue;
            }

            $subtotal += $lineTotal;
        }

        $currencyCodes = array_keys($currencies);

        return [
            'success' => empty($issues),
            'message' => empty($issues) ? 'Quote created successfully.' : 'Quote contains validation issues.',
            'data' => [
                'items' => $lines,
                'subtotal' => $this->formatDecimal($subtotal),
                'currency' => count($currencyCodes) === 1 ? $currencyCodes[0] : null,
                'currencies' => $currencyCodes,
                'can_reserve' => empty($issues),
            ],
            'errors' => $issues,
        ];
    }

    public function assertReservable(array $items): array
    {
        $quote = $this->quote($items);

        if (! $quote['success']) {
            throw ValidationException::withMessages([
                'items' => array_map(
                    static fn (array $issue): string => $issue['reason'],
                    $quote['errors'],
                ),
            ]);
        }

        if (count($quote['data']['currencies']) > 1) {
            throw ValidationException::withMessages([
                'items' => ['All products in a reservation must use the same currency.'],
            ]);
        }

        return $quote;
    }

    protected function normalizeItems(array $items): Collection
    {
        $normalized = collect($items)
            ->map(function (array $item): array {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($productId <= 0 || $quantity <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Each item must include a valid product_id and a quantity greater than zero.'],
                    ]);
                }

                return [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ];
            })
            ->groupBy('product_id')
            ->map(fn (Collection $group): int => $group->sum('quantity'));

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => ['At least one item is required.'],
            ]);
        }

        return $normalized;
    }

    protected function getAvailableQuantities(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $reservedQuantities = ReservationItem::query()
            ->selectRaw('product_id, SUM(quantity) as reserved_quantity')
            ->whereIn('product_id', $productIds)
            ->whereHas('reservation', function ($query): void {
                $query
                    ->where('status', ReservationStatus::Active)
                    ->where('expires_at', '>', now());
            })
            ->groupBy('product_id')
            ->pluck('reserved_quantity', 'product_id');

        return Product::query()
            ->with('stock')
            ->whereIn('id', $productIds)
            ->get()
            ->mapWithKeys(function (Product $product) use ($reservedQuantities): array {
                $physicalQuantity = (int) ($product->stock?->quantity ?? 0);
                $reservedQuantity = (int) ($reservedQuantities[$product->id] ?? 0);

                return [
                    $product->id => max(0, $physicalQuantity - $reservedQuantity),
                ];
            })
            ->all();
    }

    protected function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
