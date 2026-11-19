<?php

namespace Database\Seeders;

use App\Models\TransactionType;
use Illuminate\Database\Seeder;

class TransactionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (TransactionType::BUILT_IN_TYPES as $code => $name) {
            TransactionType::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
