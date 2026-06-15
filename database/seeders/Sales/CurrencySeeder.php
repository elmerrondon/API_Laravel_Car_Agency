<?php

namespace Database\Seeders\Sales;

use App\Models\Sales\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            ["name" => "Dolar","code" => "USD","is_active" => true, "is_base" => true],
            ["name" => "Euro","code" => "EUR","is_active" => true, "is_base" => false],
            ["name" => "Peso Colombiano","code" => "CPO","is_active" => true, "is_base" => false],
            ["name" => "Bolivar","code" => "BSF","is_active" => true, "is_base" => false],
        ];

        foreach($currencies as $currency){
            Currency::firstorCreate(["name" => $currency["name"], "code" =>$currency["code"]], $currency);
        }
    }
}
