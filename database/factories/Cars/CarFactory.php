<?php

namespace Database\Factories\Cars;

use App\Enums\Cars\CarStatus;
use App\Models\Cars\Car;
use App\Models\Cars\CarModel;
use App\Models\Cars\CarType;
use App\Models\Cars\Category;
use App\Models\Cars\Color;
use App\Models\Locations\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    protected $model = Car::class;


    public function definition(): array
    {
        return [
            "price" => fake()->randomFloat(2, 1000, 100000),
            "mileage" => fake()->numberBetween(0, 15000),
            "year" => fake()->numberBetween(2015,2026),
            "vin" => fake()->unique()->bothify("???##?##?######??"),
            "status" => CarStatus::AVAILABLE->value,
            "color_id" => Color::inrandomOrder()->first()->id,
            "car_model_id" => CarModel::inRandomOrder()->first()->id,
            "car_type_id" => CarType::inRandomOrder()->first()->id,
            "category_id" => Category::inRandomOrder()->first()->id,
            "branch_id" => Branch::inRandomOrder()->first()->id
        ];
    }
}
