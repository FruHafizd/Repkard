<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Listrik & air', 'Gaji & honor', 'Sewa tempat', 'Perawatan & servis alat',
            'Stok barang kasir', 'Kebersihan', 'Promosi', 'Lainnya',
        ] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
    }
}
