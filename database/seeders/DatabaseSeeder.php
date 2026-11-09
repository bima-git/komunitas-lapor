<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Komunitas',
                'role' => 'admin',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'warga@example.com'],
            [
                'name' => 'Warga Uji',
                'role' => 'warga',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'bambangbima3000@gmail.com'],
            [
                'name' => 'Bambang',
                'role' => 'admin',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
    }
}
