<?php

namespace Database\Seeders\Sales;

use App\Models\Sales\Taxe;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TaxeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $taxes = [
            ["name" => "IVA","code" => "I0001","percentage" => 16, "is_active" => true],
            ["name" => "Venta","code" => "I0002","percentage" => 10, "is_active" => true],
            ["name" => "Ambiental","code" => "I0003","percentage" => 5, "is_active" => true],
        ];

        foreach($taxes as $taxe){
            Taxe::firstOrCreate(["name" => $taxe["name"], "code" => $taxe["code"]], $taxe);
        }
    }
}
