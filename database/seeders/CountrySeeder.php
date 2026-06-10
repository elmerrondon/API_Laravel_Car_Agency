<?php

namespace Database\Seeders;

use App\Models\Locations\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            ["name" => "Venezuela"],
            ["name" => "Colombia"],
            ["name" => "Estados Unidos"],
            ["name" => "Chile"],
            ["name" => "Argentina"],
            ["name" => "España"],
            ["name" => "Brasil"],
            ["name" => "Mexico"]
            ];

           if(count($countries)>0){ 
            foreach($countries as $country){
                Country::firstOrCreate($country);
            }
            }
    }
}
