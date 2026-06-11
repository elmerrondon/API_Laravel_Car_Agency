<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\Color;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ["name" => "Negro"],
            ["name" => "Blanco"],
            ["name" => "Gris"],
            ["name" => "Marron"],
            ["name" => "Rojo"],
            ["name" => "Azul"],
            ["name" => "Verde"],
            ["name" => "Amarillo"]
        ];

        foreach($colors as $color){
            Color::firstOrCreate(["name" => $color["name"]], $color);
        }
    }
}

