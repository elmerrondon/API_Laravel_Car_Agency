<?php

namespace Database\Seeders\Cars;

use App\Models\Cars\Car;
use App\Models\Cars\CarModel;
use App\Models\Cars\CarType;
use App\Models\Cars\Category;
use App\Models\Cars\Color;
use App\Models\Locations\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $black = Color::where("name","Negro")->first();
        $white = Color::where("name","Blanco")->first();
        
        $sedan = CarType::where("name","Sedán")->first();
        $pickup = CarType::where("name","Pick-up")->first();

        $corolla = CarModel::where("name","Corolla")->first();
        $f150 = CarModel::where("name","F-150")->first();

        $family = Category::where("name","Familiar")->first();
        $work = Category::where("name","Trabajo")->first();

        $branch1 = Branch::where("name","Agencia Pueblo Nuevo")->first();
        $branch2 = Branch::where("name","Sede Ventura Plaza")->first();

        $dataCars = [$sedan,$pickup,$black,$white,$corolla,$f150,$family,$work,$branch1,$branch2];

        if(!in_array(null,$dataCars,true)){
            $cars = [
                ["price" => "10000","mileage" => 0,"year" => 2026, "vin" => "01234567890", "status" => "Avalible", "color_id" => $black->id, "car_model_id" => $corolla->id, "car_type_id" => $sedan->id, "category_id" => $family->id, "branch_id" => $branch1->id],
                ["price" => "30000","mileage" => 0,"year" => 2026, "vin" => "01234567891", "status" => "Avalible", "color_id" => $white->id, "car_model_id" => $f150->id, "car_type_id" => $pickup->id, "category_id" => $work->id, "branch_id" => $branch2->id]
            ];

            foreach($cars as $car){
                Car::firstOrCreate($car);
            }
        }
    }
}
