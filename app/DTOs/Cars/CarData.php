<?php 

namespace App\DTOs\Cars;

use Illuminate\Foundation\Http\FormRequest;

readonly class CarData{
    public function __construct(public ?float $price = null, public ?float $mileage = null, public ?int $year = null, public ?string $vin = null, public ?string $status = null, public ?int $colorId = null, public ?int $carModelId = null, public ?int $carTypeId = null, public ?int $categoryId = null, public ?int $branchId = null)
    {
        
    }

    public static function fromRequest(FormRequest $request) : self{
        return new self(price: $request->validated('price'), mileage: $request->validated('mileage'), year: $request->validated('year'), vin: $request->validated('vin'), status: $request->validated('status'), colorId: $request->validated('color_id'), carModelId: $request->validated('car_model_id'), carTypeId: $request->validated('car_type_id'), categoryId: $request->validated('category_id'), branchId: $request->validated('branch_id'));
    }
}