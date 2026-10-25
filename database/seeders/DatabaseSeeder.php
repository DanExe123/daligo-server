<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'customer@daligo.test'],
            ['name' => 'Test Customer', 'password' => 'password123', 'role' => 'customer']
        );

        User::updateOrCreate(
            ['email' => 'rider@daligo.test'],
            ['name' => 'Test Rider', 'password' => 'password123', 'role' => 'rider']
        );
    }
}