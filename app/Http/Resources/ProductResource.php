<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'price' => $this->whenLoaded('latestPrice', function (): array {
                return [
                    'amount' => $this->latestPrice?->amount,
                    'currency' => $this->latestPrice?->currency,
                ];
            }),
            'stock' => $this->whenLoaded('stock', function (): array {
                return [
                    'quantity' => $this->stock?->quantity ?? 0,
                    'updated_at' => $this->stock?->updated_at?->toISOString(),
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
