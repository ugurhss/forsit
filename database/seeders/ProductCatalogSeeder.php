<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStock;
use Illuminate\Database\Seeder;

class ProductCatalogSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach ($this->catalog() as $item) {
            $product = Product::updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'is_active' => $item['is_active'],
                ],
            );

            ProductStock::updateOrCreate(
                ['product_id' => $product->id],
                ['quantity' => $item['stock']],
            );

            $currentPrice = $product->latestPrice;

            if (! $currentPrice || $currentPrice->amount !== $item['price'] || $currentPrice->currency !== $item['currency']) {
                ProductPrice::create([
                    'product_id' => $product->id,
                    'amount' => $item['price'],
                    'currency' => $item['currency'],
                ]);
            }
        }
    }

    /**
     * @return array<int, array{name: string, sku: string, description: string, is_active: bool, stock: int, price: string, currency: string}>
     */
    protected function catalog(): array
    {
        return [
            [
                'name' => 'Forsit Desk Lamp',
                'sku' => 'FS-LAMP-001',
                'description' => 'Adjustable aluminum desk lamp for focused workspace lighting.',
                'is_active' => true,
                'stock' => 18,
                'price' => '1249.90',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Monitor Stand',
                'sku' => 'FS-STAND-002',
                'description' => 'Walnut-finish monitor stand with integrated cable channel.',
                'is_active' => true,
                'stock' => 12,
                'price' => '899.50',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Keyboard Tray',
                'sku' => 'FS-TRAY-003',
                'description' => 'Minimal slide-out tray designed for compact desk setups.',
                'is_active' => true,
                'stock' => 7,
                'price' => '649.00',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Acoustic Panel Set',
                'sku' => 'FS-PANEL-004',
                'description' => 'Sound-dampening wall panel pack for focused home offices.',
                'is_active' => true,
                'stock' => 20,
                'price' => '1540.00',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Cable Dock',
                'sku' => 'FS-DOCK-005',
                'description' => 'Weighted cable dock that keeps power leads fixed in place.',
                'is_active' => true,
                'stock' => 34,
                'price' => '219.90',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Ergonomic Footrest',
                'sku' => 'FS-REST-006',
                'description' => 'Soft-angled footrest with non-slip base and washable cover.',
                'is_active' => true,
                'stock' => 11,
                'price' => '579.00',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Task Chair Headrest',
                'sku' => 'FS-HEAD-007',
                'description' => 'Clip-on breathable headrest compatible with Forsit task chairs.',
                'is_active' => true,
                'stock' => 9,
                'price' => '780.00',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Underdesk Drawer',
                'sku' => 'FS-DRAW-008',
                'description' => 'Compact drawer unit for stationery and cable accessories.',
                'is_active' => true,
                'stock' => 5,
                'price' => '710.25',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Notebook Sleeve',
                'sku' => 'FS-SLEEVE-009',
                'description' => 'Felt-lined sleeve sized for tablets and thin notebooks.',
                'is_active' => true,
                'stock' => 40,
                'price' => '349.90',
                'currency' => 'TRY',
            ],
            [
                'name' => 'Forsit Prototype Shelf',
                'sku' => 'FS-SHELF-010',
                'description' => 'Limited run display shelf kept in catalog for archived reservations.',
                'is_active' => false,
                'stock' => 3,
                'price' => '990.00',
                'currency' => 'TRY',
            ],
        ];
    }
}
