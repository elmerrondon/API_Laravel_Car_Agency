<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\CarType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CarTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $carTypes = [
         [
             "name" => "Sedán", 
             "description" => "Vehículo clásico de tres volúmenes (motor, cabina y maletero separados). Ideal para uso ejecutivo y familiar por su confort."
         ],
         [
             "name" => "Hatchback", 
             "description" => "Vehículo compacto de dos volúmenes con puerta trasera integrada al vidrio. Excelente para la ciudad y muy fácil de estacionar."
         ],
         [
             "name" => "SUV", 
             "description" => "Vehículo Utilitario Deportivo (Sport Utility Vehicle). Combina el espacio interior de una minivan con la altura y aspecto de un todoterreno."
         ],
         [
             "name" => "Pick-up", 
             "description" => "Camioneta con cabina para pasajeros y una batea o caja descubierta en la parte trasera, diseñada para trabajo y carga."
         ],
         [
             "name" => "Coupé", 
             "description" => "Vehículo de corte deportivo, usualmente de dos puertas, con un techo que se inclina suavemente hacia la parte trasera."
         ],
         [
             "name" => "Crossover", 
             "description" => "Vehículo construido sobre la plataforma de un auto estándar, pero que adopta la estética elevada y características de una SUV."
         ],
         [
             "name" => "Minivan", 
             "description" => "Monovolumen familiar altamente espacioso, enfocado en el confort de los pasajeros, generalmente con tres filas de asientos."
         ],
         [
             "name" => "Furgoneta", 
             "description" => "Vehículo comercial cerrado (Van) sin ventanas traseras, diseñado específicamente para logística y transporte de mercancías."
         ],
         [
             "name" => "Convertible", 
             "description" => "Vehículo deportivo con techo retráctil (rígido o de lona), diseñado para disfrutar de la conducción al aire libre."
         ]
       ];

        foreach($carTypes as $type){
            CarType::firstOrCreate(["name" => $type["name"]], $type);
        }
    }
}
