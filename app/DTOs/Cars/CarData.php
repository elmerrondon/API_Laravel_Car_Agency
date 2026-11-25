<?php

declare(strict_types=1);

namespace App\DTOs\Cars;

use App\DTOs\Concerns\NormalizesRequestValues;
use Illuminate\Foundation\Http\FormRequest;

readonly class CarData
{
    use NormalizesRequestValues;

    public function __construct(public ?float $price = null, public ?int $mileage = null, public ?int $year = null, public ?string $vin = null, public ?string $status = null, public ?int $colorId = null, public ?int $carModelId = null, public ?int $carTypeId = null, public ?int $categoryId = null, public ?int $branchId = null) {}

    public static function fromRequest(FormRequest $request): self
    {
        return new self(price: self::number($request, 'price'), mileage: self::integer($request, 'mileage'), year: self::integer($request, 'year'), vin: self::string($request, 'vin'), status: self::string($request, 'status'), colorId: self::integer($request, 'color_id'), carModelId: self::integer($request, 'car_model_id'), carTypeId: self::integer($request, 'car_type_id'), categoryId: self::integer($request, 'category_id'), branchId: self::integer($request, 'branch_id'));
    }
}
