<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Cars\BrandSeeder;
use Database\Seeders\Locations\BranchSeeder;
use Database\Seeders\Locations\CitySeeder;
use Database\Seeders\Locations\CountrySeeder;
use Database\Seeders\Locations\StateSeeder;
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
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Locations 
        $this->call([
            CountrySeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            BranchSeeder::class
        ]);

        // Cars
        $this->call([
            BrandSeeder::class
        ]);
    }
}
