<?php

namespace Database\Seeders\Sales;

use App\Models\Sales\PaymentMethod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            ["name" => "Efectivo", "is_active" => true],
            ["name" => "Tarjeta", "is_active" => true],
            ["name" => "Crypto", "is_active" => true],
            ["name" => "Transferencia", "is_active" => true],
            ["name" => "Pago Movil", "is_active" => true]
        ];

        foreach($paymentMethods as $method){
            PaymentMethod::firstOrCreate(["name" => $method["name"]], $method);
        }
    }
}
