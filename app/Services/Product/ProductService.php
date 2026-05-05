<?php

namespace App\Services\Product;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function listProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['latestPrice', 'stock']);

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyFilters($query, $filters);
        $this->applySorting(
            $query,
            $filters['sort_by'] ?? 'created_at',
            $filters['sort_direction'] ?? 'desc',
        );

        $perPage = min(max((int) ($filters['per_page'] ?? 15), 1), 100);

        return $query
            ->paginate($perPage)
            ->appends($filters);
    }

    protected function applySearch(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false);
        }

        if (array_key_exists('in_stock', $filters) && $filters['in_stock'] !== null) {
            $inStock = filter_var($filters['in_stock'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;

            if ($inStock) {
                $query->whereHas('stock', function (Builder $builder): void {
                    $builder->where('quantity', '>', 0);
                });
            } else {
                $query->where(function (Builder $builder): void {
                    $builder
                        ->whereDoesntHave('stock')
                        ->orWhereHas('stock', function (Builder $stockBuilder): void {
                            $stockBuilder->where('quantity', '<=', 0);
                        });
                });
            }
        }

        if (! empty($filters['currency'])) {
            $query->whereHas('latestPrice', function (Builder $builder) use ($filters): void {
                $builder->where('currency', $filters['currency']);
            });
        }

        if (isset($filters['min_price'])) {
            $query->whereHas('latestPrice', function (Builder $builder) use ($filters): void {
                $builder->where('amount', '>=', $filters['min_price']);
            });
        }

        if (isset($filters['max_price'])) {
            $query->whereHas('latestPrice', function (Builder $builder) use ($filters): void {
                $builder->where('amount', '<=', $filters['max_price']);
            });
        }
    }

    protected function applySorting(Builder $query, string $sortBy, string $direction): void
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        match ($sortBy) {
            'name', 'sku', 'created_at' => $query->orderBy($sortBy, $direction),
            'price' => $query->orderBy(
                DB::table('product_prices')
                    ->select('amount')
                    ->whereColumn('product_prices.product_id', 'products.id')
                    ->latest('id')
                    ->limit(1),
                $direction,
            ),
            'stock' => $query->orderBy(
                DB::table('product_stocks')
                    ->select('quantity')
                    ->whereColumn('product_stocks.product_id', 'products.id')
                    ->limit(1),
                $direction,
            ),
            default => $query->orderBy('created_at', 'desc'),
        };
    }
}
