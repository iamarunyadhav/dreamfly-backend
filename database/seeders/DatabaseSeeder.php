<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@dreamfly.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'status' => 'active',
                'job_title' => 'Director',
            ],
        );

        if (! $superAdmin->hasRole('Super Admin')) {
            $superAdmin->assignRole('Super Admin');
        }
    }
}
