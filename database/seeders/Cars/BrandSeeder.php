<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\Brand;
use App\Models\Locations\Country;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $usa = Country::where("name","Estados Unidos")->first();
        $japan = Country::where("name","Japon")->first();
        $china = Country::where("name","China")->first();
        $southKorea = Country::where("name","Corea del Sur")->first();
        $france = Country::where("name","Francia")->first();
        $germany = Country::where("name","Alemania")->first();
        $italy = Country::where("name","Italia")->first();

        if($usa && $japan && $china && $southKorea && $france && $germany && $italy){
            $brands = [
             // --- JAPÓN ---
             ["name" => "Toyota", "description" => "Líder mundial, reconocida por su durabilidad y fiabilidad.", "country_id" => $japan->id],
             ["name" => "Honda", "description" => "Innovación en motores eficientes y vehículos de uso diario.", "country_id" => $japan->id],
             ["name" => "Nissan", "description" => "Pioneros en movilidad eléctrica y vehículos comerciales.", "country_id" => $japan->id],
             ["name" => "Mazda", "description" => "Diseño elegante (Kodo) y experiencia de conducción premium.", "country_id" => $japan->id],
     
             // --- ESTADOS UNIDOS ---
             ["name" => "Ford", "description" => "Creadores de la legendaria Serie F y pioneros en producción masiva.", "country_id" => $usa->id],
             ["name" => "Chevrolet", "description" => "Marca icónica americana con amplia gama de SUVs y Pick-ups.", "country_id" => $usa->id],
             ["name" => "Tesla", "description" => "Líder indiscutible en vehículos 100% eléctricos y tecnología autónoma.", "country_id" => $usa->id],
             ["name" => "Jeep", "description" => "Especialistas globales en vehículos todoterreno y aventura.", "country_id" => $usa->id],
     
             // --- ALEMANIA ---
             ["name" => "Volkswagen", "description" => "Mayor fabricante europeo, balance perfecto entre calidad y precio.", "country_id" => $germany->id],
             ["name" => "BMW", "description" => "Vehículos de lujo enfocados en el dinamismo y placer de conducir.", "country_id" => $germany->id],
             ["name" => "Mercedes-Benz", "description" => "Sinónimo mundial de lujo, confort, estatus y tecnología de punta.", "country_id" => $germany->id],
             ["name" => "Audi", "description" => "Diseño sofisticado, tecnología quattro y acabados de altísima calidad.", "country_id" => $germany->id],
     
             // --- COREA DEL SUR ---
             ["name" => "Hyundai", "description" => "Crecimiento acelerado con diseños futuristas y gran tecnología.", "country_id" => $southKorea->id],
             ["name" => "Kia", "description" => "Vehículos atractivos, juveniles y con excelente relación valor-precio.", "country_id" => $southKorea->id],
     
             // --- CHINA ---
             ["name" => "BYD", "description" => "Gigante mundial en vehículos eléctricos y tecnología híbrida enchufable de última generación.", "country_id" => $china->id],
             ["name" => "JAC", "description" => "Fuerte presencia nacional con una amplia línea de vehículos de pasajeros, comerciales y de carga.", "country_id" => $china->id],
             ["name" => "Dongfeng", "description" => "Reconocida por su robustez en vehículos pesados y opciones accesibles para uso particular.", "country_id" => $china->id],
     
             // --- FRANCIA ---
             ["name" => "Peugeot", "description" => "Diseño vanguardista, interiores innovadores y enfoque en la elegancia.", "country_id" => $france->id],
             ["name" => "Renault", "description" => "Vehículos prácticos, compactos y de gran rendimiento para la ciudad.", "country_id" => $france->id],
     
             // --- ITALIA ---
             ["name" => "Fiat", "description" => "Especialistas en autos compactos y urbanos con estilo europeo.", "country_id" => $italy->id],
             ["name" => "Ferrari", "description" => "El pináculo de los superdeportivos, la exclusividad y la Fórmula 1.", "country_id" => $italy->id],
             ["name" => "Alfa Romeo", "description" => "Diseño pasional italiano y espíritu altamente deportivo.", "country_id" => $italy->id],
             ];

             foreach($brands as $brand){
                Brand::firstOrCreate(["name" => $brand["name"]], $brand);
             }
        }
    }
}
