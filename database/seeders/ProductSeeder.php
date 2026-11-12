<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['name' => 'Air mineral 600ml', 'price' => 5000, 'track_stock' => true, 'stock' => 50],
            ['name' => 'Minuman isotonik', 'price' => 10000, 'track_stock' => true, 'stock' => 30],
            ['name' => 'Protein shake', 'price' => 25000, 'track_stock' => true, 'stock' => 20],
            ['name' => 'Sewa handuk', 'price' => 5000, 'track_stock' => false, 'stock' => 0],
        ];

        foreach ($products as $index => $product) {
            Product::firstOrCreate(['name' => $product['name']], $product + ['sort_order' => $index]);
        }
    }
}
