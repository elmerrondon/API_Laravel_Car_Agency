<?php

namespace Database\Seeders\Locations;

use App\Models\Locations\Branch;
use App\Models\Locations\City;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sanCristobal = City::where("name", "San Cristobal")->first();
        $merida       = City::where("name", "Merida")->first();
        $maracaibo    = City::where("name", "Maracaibo")->first();
        $cucuta       = City::where("name", "Cucuta")->first();
        $medellin     = City::where("name", "Medellin")->first();
        $dallas       = City::where("name", "Dallas")->first();
        $sanDiego     = City::where("name", "San Diego")->first();


      if($sanCristobal && $merida && $maracaibo && $cucuta && $medellin && $dallas && $sanDiego){
        
        $branches = [
        // --- VENEZUELA ---
        [
            "name"        => "Agencia Pueblo Nuevo",
            "code"        => "001987654321",
            "address"     => "Avenida Principal de Pueblo Nuevo, a 100mts de la panadería",
            "description" => "Sede principal y administrativa del Estado Táchira.",
            "zip_code"    => "5001",
            "is_active"   => true,
            "city_id"     => $sanCristobal->id
        ],
        [
            "name"        => "Sucursal Barrio Obrero",
            "code"        => "001987654322",
            "address"     => "Carrera 20 con Calle 10, Barrio Obrero, cerca de la Plaza Los Mangos",
            "description" => "Punto de ventas express en la zona comercial de San Cristóbal.",
            "zip_code"    => "5001",
            "is_active"   => true,
            "city_id"     => $sanCristobal->id
        ],
        [
            "name"        => "Concesionario Las Américas",
            "code"        => "001987654323",
            "address"     => "Avenida Las Américas, Sector Santa Bárbara, CC Mercado Principal",
            "description" => "Sede andina con exhibición de vehículos 4x4.",
            "zip_code"    => "5101",
            "is_active"   => true,
            "city_id"     => $merida->id
        ],
        [
            "name"        => "Agencia 5 de Julio",
            "code"        => "001987654324",
            "address"     => "Boulevard 5 de Julio (Calle 77), entre Delicias y Bella Vista",
            "description" => "Sucursal occidente, especializada en vehículos de carga y comerciales.",
            "zip_code"    => "4001",
            "is_active"   => true,
            "city_id"     => $maracaibo->id
        ],

        // --- COLOMBIA ---
        [
            "name"        => "Sede Ventura Plaza",
            "code"        => "002123456789",
            "address"     => "Calle 10 y 11 Diagonal Santander, Barrio Caobos",
            "description" => "Centro de ventas fronterizo Norte de Santander.",
            "zip_code"    => "540001", // Colombia usa 6 dígitos
            "is_active"   => true,
            "city_id"     => $cucuta->id
        ],
        [
            "name"        => "Concesionario Milla de Oro",
            "code"        => "002123456790",
            "address"     => "Carrera 43A, El Poblado, cerca del Centro Comercial Santafé",
            "description" => "Showroom de vehículos de lujo para el área metropolitana de Antioquia.",
            "zip_code"    => "050022",
            "is_active"   => true,
            "city_id"     => $medellin->id
        ],

        // --- ESTADOS UNIDOS ---
        [
            "name"        => "Downtown Dallas Branch",
            "code"        => "003112233445",
            "address"     => "1200 Main St, Dallas, TX",
            "description" => "Texas regional headquarters and sales center.",
            "zip_code"    => "75201", // USA usa 5 dígitos estándar
            "is_active"   => true,
            "city_id"     => $dallas->id
        ],
        [
            "name"        => "Gaslamp Auto Plaza",
            "code"        => "003112233446",
            "address"     => "5th Avenue and Broadway, San Diego, CA",
            "description" => "West Coast electric and hybrid vehicle showroom.",
            "zip_code"    => "92101",
            "is_active"   => true,
            "city_id"     => $sanDiego->id
        ]
    ];

    
        foreach($branches as $branch){
        Branch::firstOrCreate(["code" => $branch["code"]], $branch);
    }
    }
  }
}
