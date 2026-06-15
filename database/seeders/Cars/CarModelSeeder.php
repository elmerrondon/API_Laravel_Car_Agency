<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\Brand;
use App\Models\Cars\CarModel;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CarModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $toyota    = Brand::where("name", "Toyota")->first();
        $honda     = Brand::where("name", "Honda")->first();
        $nissan    = Brand::where("name", "Nissan")->first();
        $mazda     = Brand::where("name", "Mazda")->first();
        
        $ford      = Brand::where("name", "Ford")->first();
        $chevrolet = Brand::where("name", "Chevrolet")->first();
        $tesla     = Brand::where("name", "Tesla")->first();
        $jeep      = Brand::where("name", "Jeep")->first();

        $vw        = Brand::where("name", "Volkswagen")->first();
        $bmw       = Brand::where("name", "BMW")->first();
        $mercedes  = Brand::where("name", "Mercedes-Benz")->first();
        $audi      = Brand::where("name", "Audi")->first();

        $hyundai   = Brand::where("name", "Hyundai")->first();
        $kia       = Brand::where("name", "Kia")->first();

        $byd       = Brand::where("name", "BYD")->first();
        $jac       = Brand::where("name", "JAC")->first();
        $dongfeng  = Brand::where("name", "Dongfeng")->first();

        $peugeot   = Brand::where("name", "Peugeot")->first();
        $renault   = Brand::where("name", "Renault")->first();

        $fiat      = Brand::where("name", "Fiat")->first();
        $ferrari   = Brand::where("name", "Ferrari")->first();
        $alfa      = Brand::where("name", "Alfa Romeo")->first();

        $allBrands = [
            $toyota, $honda, $nissan, $mazda, $ford, $chevrolet, $tesla, $jeep, 
            $vw, $bmw, $mercedes, $audi, $hyundai, $kia, $byd, $jac, $dongfeng, 
            $peugeot, $renault, $fiat, $ferrari, $alfa
        ];

        if (!in_array(null, $allBrands, true)) {
            
            $models = [
                // --- TOYOTA ---
                ["name" => "Corolla", "description" => "El sedán más vendido de la historia, sinónimo de confiabilidad.", "brand_id" => $toyota->id],
                ["name" => "Hilux", "description" => "Pick-up indestructible, líder absoluta en el mercado de trabajo.", "brand_id" => $toyota->id],
                ["name" => "Fortuner", "description" => "SUV robusta basada en el chasis de la Hilux, ideal para terrenos exigentes.", "brand_id" => $toyota->id],

                // --- HONDA & NISSAN & MAZDA ---
                ["name" => "Civic", "description" => "Sedán compacto con diseño deportivo y excelente valor de reventa.", "brand_id" => $honda->id],
                ["name" => "Sentra", "description" => "Sedán espacioso y económico, un clásico de la marca.", "brand_id" => $nissan->id],
                ["name" => "Frontier", "description" => "Pick-up de trabajo pesado con excelente capacidad de carga.", "brand_id" => $nissan->id],
                ["name" => "Mazda3", "description" => "Hatchback y sedán con acabados premium y diseño Kodo.", "brand_id" => $mazda->id],

                // --- ESTADOS UNIDOS ---
                ["name" => "F-150", "description" => "La serie de camionetas más icónica y vendida de Ford.", "brand_id" => $ford->id],
                ["name" => "Explorer", "description" => "SUV familiar amplia y potente para viajes largos.", "brand_id" => $ford->id],
                ["name" => "Silverado", "description" => "Camioneta full-size de gran potencia para carga y remolque.", "brand_id" => $chevrolet->id],
                ["name" => "Spark", "description" => "Vehículo compacto urbano, líder en economía de combustible.", "brand_id" => $chevrolet->id],
                ["name" => "Model 3", "description" => "El sedán eléctrico que revolucionó el mercado global.", "brand_id" => $tesla->id],
                ["name" => "Grand Cherokee", "description" => "SUV premium que combina lujo con capacidades off-road reales.", "brand_id" => $jeep->id],

                // --- ALEMANIA ---
                ["name" => "Jetta", "description" => "Sedán ejecutivo compacto con sólida ingeniería alemana.", "brand_id" => $vw->id],
                ["name" => "Amarok", "description" => "Pick-up de alta gama con enfoque en confort y potencia.", "brand_id" => $vw->id],
                ["name" => "Serie 3", "description" => "El estándar de oro en sedanes deportivos de lujo.", "brand_id" => $bmw->id],
                ["name" => "Clase C", "description" => "Elegancia y tecnología heredadas de los modelos tope de gama.", "brand_id" => $mercedes->id],
                ["name" => "A4", "description" => "Sedán premium con tracción quattro opcional y diseño sobrio.", "brand_id" => $audi->id],

                // --- COREA DEL SUR ---
                ["name" => "Tucson", "description" => "SUV compacta con diseño disruptivo y alta tecnología.", "brand_id" => $hyundai->id],
                ["name" => "Sportage", "description" => "Hermana de la Tucson, destacada por su estilo audaz.", "brand_id" => $kia->id],
                ["name" => "Rio", "description" => "Subcompacto ágil, duradero y de bajo mantenimiento.", "brand_id" => $kia->id],

                // --- CHINA ---
                ["name" => "Dolphin", "description" => "Hatchback 100% eléctrico, accesible y con excelente autonomía urbana.", "brand_id" => $byd->id],
                ["name" => "Song Plus", "description" => "SUV híbrida enchufable (DM-i) de altísima eficiencia y lujo interior.", "brand_id" => $byd->id],
                ["name" => "T8", "description" => "Pick-up de trabajo robusta con gran relación precio-equipamiento.", "brand_id" => $jac->id],
                ["name" => "JS4", "description" => "SUV compacta urbana con diseño moderno y gran espacio interior.", "brand_id" => $jac->id],
                ["name" => "Rich 6", "description" => "Pick-up desarrollada en conjunto con tecnología japonesa, muy resistente.", "brand_id" => $dongfeng->id],

                // --- FRANCIA E ITALIA ---
                ["name" => "208", "description" => "Hatchback urbano con el distintivo i-Cockpit de Peugeot.", "brand_id" => $peugeot->id],
                ["name" => "Duster", "description" => "SUV sumamente popular, guerrera y económica para la región.", "brand_id" => $renault->id],
                ["name" => "Logan", "description" => "Sedán familiar con un espacio de baúl insuperable en su segmento.", "brand_id" => $renault->id],
                ["name" => "Argo", "description" => "Hatchback moderno con diseño italiano y mecánica confiable.", "brand_id" => $fiat->id],
                ["name" => "F8 Tributo", "description" => "Motor V8 galardonado, obra maestra de la ingeniería italiana.", "brand_id" => $ferrari->id],
                ["name" => "Giulia", "description" => "Sedán deportivo con un chasis excepcionalmente dinámico.", "brand_id" => $alfa->id],
            ];

            foreach ($models as $model) {
                CarModel::firstOrCreate(['name' => $model['name']], $model);
            }
        }
    }
}
