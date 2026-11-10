<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('SUPERADMIN_PASSWORD');

        if (empty($password)) {
            throw new \RuntimeException(
                'Set SUPERADMIN_PASSWORD in your .env file before seeding.'
            );
        }

        User::updateOrCreate(
            ['username' => 'adminvon'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
                'role' => 'superadmin',
                'status' => 'active',
            ]
        );
    }
}
