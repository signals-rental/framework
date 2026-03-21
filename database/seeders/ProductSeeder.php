<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Enums\StockMethod;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $groups = ProductGroup::query()->pluck('id', 'name');

        $products = [
            // Audio
            [
                'name' => 'Shure SM58',
                'description' => 'Dynamic vocal microphone, industry standard for live sound.',
                'product_group_id' => $groups->get('Audio'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 8900,
                'sku' => 'AUD-SM58',
            ],
            [
                'name' => 'JBL EON615',
                'description' => '15" two-way multipurpose self-powered sound reinforcement speaker.',
                'product_group_id' => $groups->get('Audio'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 39900,
                'weight' => 18.1400,
                'sku' => 'AUD-EON615',
            ],
            [
                'name' => 'Yamaha TF1 Mixing Console',
                'description' => '40-input digital mixing console with TouchFlow operation.',
                'product_group_id' => $groups->get('Audio'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 299900,
                'weight' => 17.2000,
                'sku' => 'AUD-TF1',
            ],
            // Lighting - Moving Heads
            [
                'name' => 'Robe Esprite',
                'description' => 'LED moving head profile fixture, 600W white LED engine.',
                'product_group_id' => $groups->get('Lighting - Moving Heads'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 899900,
                'weight' => 27.5000,
                'sku' => 'LMH-ESPRITE',
            ],
            [
                'name' => 'Martin MAC Aura XB',
                'description' => 'Compact LED wash light with Aura backlight effect.',
                'product_group_id' => $groups->get('Lighting - Moving Heads'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 349900,
                'weight' => 6.8000,
                'sku' => 'LMH-AURAXB',
            ],
            // Lighting - Generic
            [
                'name' => 'Chauvet COLORado Panel Q40',
                'description' => 'RGBW LED wash panel for stage and architectural lighting.',
                'product_group_id' => $groups->get('Lighting - Generic'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 149900,
                'sku' => 'LGN-CPQ40',
            ],
            [
                'name' => 'ETC Source Four 750W',
                'description' => 'Ellipsoidal reflector spotlight, 750W HPL lamp.',
                'product_group_id' => $groups->get('Lighting - Generic'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 44900,
                'sku' => 'LGN-S4-750',
            ],
            // Video
            [
                'name' => 'Barco UDX-4K32',
                'description' => '31,000 lumens 4K UHD laser projector.',
                'product_group_id' => $groups->get('Video'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 9999900,
                'weight' => 75.0000,
                'sku' => 'VID-UDX4K32',
            ],
            [
                'name' => 'Samsung 55" LED Display',
                'description' => '55" professional LED display panel for video walls.',
                'product_group_id' => $groups->get('Video'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 189900,
                'weight' => 18.6000,
                'sku' => 'VID-SAM55',
            ],
            // Staging
            [
                'name' => 'Stage Deck 8x4',
                'description' => '8ft x 4ft aluminium stage deck panel.',
                'product_group_id' => $groups->get('Staging'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 35000,
                'weight' => 38.0000,
                'sku' => 'STG-DECK-8X4',
            ],
            [
                'name' => 'Stage Leg 1m',
                'description' => '1 metre adjustable stage leg with base plate.',
                'product_group_id' => $groups->get('Staging'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Bulk,
                'replacement_charge' => 4500,
                'sku' => 'STG-LEG-1M',
            ],
            // Power
            [
                'name' => 'Power Distribution 63A 3-Phase',
                'description' => '63A 3-phase power distribution unit with RCBOs.',
                'product_group_id' => $groups->get('Power'),
                'product_type' => ProductType::Rental,
                'stock_method' => StockMethod::Serialised,
                'replacement_charge' => 125000,
                'weight' => 24.0000,
                'sku' => 'PWR-DIST-63A',
            ],
            // Consumables (sale items)
            [
                'name' => 'Gaffer Tape 50m Black',
                'description' => '50mm x 50m black gaffer tape roll.',
                'product_group_id' => $groups->get('Consumables'),
                'product_type' => ProductType::Sale,
                'stock_method' => StockMethod::Bulk,
                'purchase_price' => 450,
                'sku' => 'CON-TAPE-BLK',
                'discountable' => false,
            ],
            // Service items
            [
                'name' => 'Lighting Technician',
                'description' => 'Qualified lighting technician — per day rate.',
                'product_group_id' => null,
                'product_type' => ProductType::Service,
                'stock_method' => StockMethod::Bulk,
                'sku' => 'SVC-LX-TECH',
            ],
        ];

        foreach ($products as $productData) {
            Product::create(array_merge([
                'is_active' => true,
            ], $productData));
        }
    }
}
