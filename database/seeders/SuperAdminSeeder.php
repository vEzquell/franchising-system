<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

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

        // Ensure Superadmin role exists
        Role::firstOrCreate([
            'name' => 'superadmin',
            'guard_name' => 'web',
        ]);

        // Create or update Superadmin
        $user = User::updateOrCreate(
            ['username' => 'adminvon'],
            [
                'name' => 'System Administrator',
                'password' => $password,
                'status' => 'active',
            ]
        );

        // Assign Superadmin role
        $user->syncRoles(['superadmin']);
    }
}