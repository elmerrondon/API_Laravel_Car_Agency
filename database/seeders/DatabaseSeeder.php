<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Cars\BrandSeeder;
use Database\Seeders\Cars\CarModelSeeder;
use Database\Seeders\Cars\CarSeeder;
use Database\Seeders\Cars\CarTypeSeeder;
use Database\Seeders\Cars\CategorySeeder;
use Database\Seeders\Cars\ColorSeeder;
use Database\Seeders\Locations\BranchSeeder;
use Database\Seeders\Locations\CitySeeder;
use Database\Seeders\Locations\CountrySeeder;
use Database\Seeders\Locations\StateSeeder;
use Database\Seeders\Sales\CurrencySeeder;
use Database\Seeders\Sales\PaymentMethodSeeder;
use Database\Seeders\Sales\TaxeSeeder;
use Database\Seeders\Users\RoleSeeder;
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
        // Users
        $this->call([
            RoleSeeder::class
        ]);

        // Locations 
        $this->call([
            CountrySeeder::class,
            StateSeeder::class,
            CitySeeder::class,
            BranchSeeder::class
        ]);

        // Cars
        $this->call([
            BrandSeeder::class,
            CarTypeSeeder::class,
            CategorySeeder::class,
            CarModelSeeder::class,
            ColorSeeder::class,
            CarSeeder::class
        ]);

        // Sales
        $this->call([
            CurrencySeeder::class,
            PaymentMethodSeeder::class,
            TaxeSeeder::class
        ]);
    }
}
