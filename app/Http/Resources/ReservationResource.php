<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'customer_email' => $this->customer_email,
            'status' => $this->status?->value ?? $this->status,
            'subtotal' => $this->subtotal,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'items' => $this->whenLoaded('items', function (): array {
                return $this->items->map(function ($item): array {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                        'product' => $item->relationLoaded('product') ? [
                            'id' => $item->product?->id,
                            'name' => $item->product?->name,
                            'sku' => $item->product?->sku,
                            'price' => $item->product?->relationLoaded('latestPrice') ? [
                                'amount' => $item->product?->latestPrice?->amount,
                                'currency' => $item->product?->latestPrice?->currency,
                            ] : null,
                            'stock' => $item->product?->relationLoaded('stock') ? [
                                'quantity' => $item->product?->stock?->quantity ?? 0,
                            ] : null,
                        ] : null,
                    ];
                })->all();
            }),
        ];
    }
}
