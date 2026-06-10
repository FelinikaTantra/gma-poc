<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Store;
use App\Models\Product;
use App\Models\ProductCompatibility;

class MarketplaceSyncService
{
    /**
     * Simulate fetching products from Marketplace APIs and syncing to the catalog.
     */
    public function syncProducts(Company $company)
    {
        // For MVP Phase 1: We mock the API response
        $mockMarketplaceData = [
            [
                'external_id' => 'SH-001',
                'name' => 'Spion X-Max Racing (Carbon)',
                'price' => 175000,
                'stock' => 15,
                'compatibilities' => [
                    ['brand' => 'Yamaha', 'model' => 'NMAX', 'year' => '2020-2024'],
                    ['brand' => 'Yamaha', 'model' => 'XMAX', 'year' => '2022-2024']
                ]
            ],
            [
                'external_id' => 'TP-002',
                'name' => 'Kampas Rem Depan Vario 125/150 Original',
                'price' => 45000,
                'stock' => 120,
                'compatibilities' => [
                    ['brand' => 'Honda', 'model' => 'Vario 125', 'year' => '2015-2023'],
                    ['brand' => 'Honda', 'model' => 'Vario 150', 'year' => '2015-2023']
                ]
            ],
            [
                'external_id' => 'TK-003',
                'name' => 'Oli Mesin Motul Scooter LE 10W-30',
                'price' => 80000,
                'stock' => 50,
                'compatibilities' => [
                    ['brand' => 'Universal', 'model' => 'Matic', 'year' => 'All']
                ]
            ]
        ];

        foreach ($mockMarketplaceData as $item) {
            $product = Product::updateOrCreate(
                ['company_id' => $company->id, 'external_product_id' => $item['external_id']],
                [
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'stock' => $item['stock'],
                ]
            );

            // Sync compatibilities
            $product->productCompatibilities()->delete(); // clear old ones
            foreach ($item['compatibilities'] as $comp) {
                $product->productCompatibilities()->create([
                    'vehicle_brand' => $comp['brand'],
                    'vehicle_model' => $comp['model'],
                    'vehicle_year'  => $comp['year'],
                ]);
            }
        }

        return count($mockMarketplaceData);
    }
}
