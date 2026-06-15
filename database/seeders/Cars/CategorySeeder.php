<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
          [
              "name" => "Familiar", 
              "description" => "Vehículos diseñados para el transporte diario de pasajeros en la ciudad y viajes familiares. Priorizan el confort y la economía."
          ],
          [
              "name" => "Trabajo", 
              "description" => "Flotas, camiones ligeros, furgonetas y pick-ups orientadas a la carga, logística y trabajo pesado empresarial."
          ],
          [
              "name" => "Premium", 
              "description" => "Vehículos de lujo que ofrecen acabados superiores, tecnología de punta, máximo confort y un estatus exclusivo."
          ],
          [
              "name" => "Eléctrico", 
              "description" => "Vehículos propulsados por energías alternativas (100% Eléctricos e Híbridos Enchufables) enfocados en la sostenibilidad."
          ],
          [
              "name" => "Deportivo", 
              "description" => "Vehículos diseñados para maximizar la velocidad, la aceleración y la aerodinámica, ofreciendo una experiencia de conducción extrema."
          ],
          [
              "name" => "Off-Road", 
              "description" => "Vehículos con tracción 4x4, suspensiones elevadas y capacidades todoterreno para rutas exigentes y fuera del asfalto."
          ]
        ];

        foreach($categories as $category){
            Category::firstOrCreate(["name" => $category["name"]], $category);
        }
    }
}
