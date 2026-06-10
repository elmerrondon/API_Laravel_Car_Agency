<?php

namespace Database\Seeders;

use App\Models\Locations\City;
use App\Models\Locations\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tachira = State::where("name", "Tachira")->first();
        $zulia = State::where("name", "Zulia")->first();
        $lara = State::where("name", "Lara")->first();
        $merida = State::where("name", "Merida")->first();
        $trujillo = State::where("name", "Trujillo")->first();
        $dc = State::where("name", "Distrito Capital")->first();
        
        $norteSantander = State::where("name", "Norte de Santander")->first();
        $santander = State::where("name", "Santander")->first();
        $antioquia = State::where("name", "Antioquia")->first();

        $florida = State::where("name", "Florida")->first();
        $california = State::where("name", "California")->first();
        $texas = State::where("name", "Texas")->first();

        if($tachira && $merida && $zulia && $lara && $trujillo && $dc && $norteSantander && $santander && $antioquia && $florida && $california && $texas){

            $cities = [
            ["name" => "San Cristobal", "state_id" => $tachira->id],
            ["name" => "Merida", "state_id" => $merida->id],
            ["name" => "Valera", "state_id" => $trujillo->id],
            ["name" => "Maracaibo", "state_id" => $zulia->id],
            ["name" => "Barquisimeto", "state_id" => $lara->id],
            ["name" => "Caracas", "state_id" => $dc->id],
            ["name" => "Cucuta", "state_id" => $norteSantander->id],
            ["name" => "Bucaramanga", "state_id" => $santander->id],
            ["name" => "Medellin", "state_id" => $antioquia->id],
            ["name" => "Miami", "state_id" => $florida->id],
            ["name" => "Tampa", "state_id" => $florida->id],
            ["name" => "San Diego", "state_id" => $california->id],
             ["name" => "San Jose", "state_id" => $california->id],
            ["name" => "Dallas", "state_id" => $texas->id]
        ];

        if(count($cities)>0){
            foreach($cities as $city){
            City::firstOrCreate($city);
        }
        }
        
      }

    }
}
