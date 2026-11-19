<?php

declare(strict_types=1);

namespace App\Models\Cars;

use App\Enums\Cars\CarStatus;
use App\Models\Locations\Branch;
use Database\Factories\Cars\CarFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['price', 'status', 'mileage', 'vin', 'year', 'color_id', 'car_model_id', 'category_id', 'car_type_id', 'branch_id'];

    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    /** @return BelongsTo<Color, $this> */
    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    /** @return BelongsTo<CarModel, $this> */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    /** @return BelongsTo<CarType, $this> */
    public function carType(): BelongsTo
    {
        return $this->belongsTo(CarType::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Branch, $this> */
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

    /**
     * El VIN se guarda siempre en mayúsculas, sin espacios sobrantes. La
     * normalización del FormRequest cubre la API; el mutador protege
     * factories, seeders y cualquier escritura directa al modelo.
     *
     * @return Attribute<string, string>
     */
    protected function vin(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::upper(trim($value)),
        );
    }
}
