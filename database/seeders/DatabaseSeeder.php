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
     *
     * O SuperAdminSeeder roda PRIMEIRO: em banco zerado isso garante que o
     * superadmin fique com id=1 (slot que dá poder extra no dashboard).
     */
    public function run(): void
    {
        $this->call([
            SuperAdminSeeder::class,
        ]);
    }
}
