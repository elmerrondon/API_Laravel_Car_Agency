<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\ColorData;
use App\Models\Cars\Color;

class ColorService
{
    public function getAllPaginated(?int $perPage = null)
    {
        return Color::select(['id', 'name', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createColor(ColorData $data): Color
    {
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color = Color::create($cleanData);

        $color->refresh();

        return $color;
    }

    public function updateColor(Color $color, ColorData $data): Color
    {
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color->update($cleanData);

        return $color;
    }

    public function deleteColor(Color $color): bool
    {
        return $color->delete();
    }
}
