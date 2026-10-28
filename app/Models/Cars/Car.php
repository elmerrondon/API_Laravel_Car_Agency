<?php

declare(strict_types=1);

namespace App\Models\Cars;

use App\Enums\Cars\CarStatus;
use App\Models\Locations\Branch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['price', 'status', 'mileage', 'vin', 'year', 'color_id', 'car_model_id', 'category_id', 'car_type_id', 'branch_id'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'year' => 'integer',
            'status' => CarStatus::class,
        ];
    }
}
