<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FranchiseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $batch = [];

        for ($i = 1; $i <= 3502; $i++) {

            // Generate SC-0001 to SC-3502
            $franchiseNumber = 'SC-' . str_pad(
                $i,
                4,
                '0',
                STR_PAD_LEFT
            );

            $batch[] = [
                'franchise_number' => $franchiseNumber,
                'status' => 'active',
                'date_issued' => null,
                'expiration_date' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Insert records in batches of 500
            if (count($batch) === 500) {
                DB::table('franchises')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            DB::table('franchises')->insertOrIgnore($batch);
        }

        $this->command->info(
            'Franchise seeding completed! SC-0001 to SC-3502.'
        );
    }
}
