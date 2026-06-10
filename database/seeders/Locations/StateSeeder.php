<?php

namespace Database\Seeders\Locations;

use App\Models\Locations\Country;
use App\Models\Locations\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $venezuela = Country::where("name", "Venezuela")->first();
        $colombia = Country::where("name", "Colombia")->first();
        $usa = Country::where("name", "Estados Unidos")->first();

        if($venezuela && $colombia && $usa){
            
            $states = [
            ["name" => "Tachira", "country_id" => $venezuela->id],
            ["name" => "Merida", "country_id" => $venezuela->id],
            ["name" => "Trujillo", "country_id" => $venezuela->id],
            ["name" => "Zulia", "country_id" => $venezuela->id],
            ["name" => "Lara", "country_id" => $venezuela->id],
            ["name" => "Carabobo", "country_id" => $venezuela->id],
            ["name" => "Distrito Capital", "country_id" => $venezuela->id],
            ["name" => "Falcon", "country_id" => $venezuela->id],
            ["name" => "Santander", "country_id" => $colombia->id],
            ["name" => "Norte de Santander", "country_id" => $colombia->id],
            ["name" => "Antioquia", "country_id" => $colombia->id],
            ["name" => "Caldas", "country_id" => $colombia->id],
            ["name" => "Cundinamarca", "country_id" => $colombia->id],
            ["name" => "Florida", "country_id" => $usa->id],
            ["name" => "California", "country_id" => $usa->id],
            ["name" => "Texas", "country_id" => $usa->id],
            ["name" => "Nuevo Mexico", "country_id" => $usa->id],
            ["name" => "Arizona", "country_id" => $usa->id]          
        ];

        if(count($states)>0){
            foreach($states as $state){
            State::firstOrCreate($state);
            } 
        }
        }
    }
}
