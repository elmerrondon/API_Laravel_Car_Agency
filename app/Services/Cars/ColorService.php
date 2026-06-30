<?php 

namespace App\Services\Cars;

use App\DTOs\Cars\ColorData;
use App\Models\Cars\Color;

class ColorService{
    public function getAllPaginated(?int $perPage = null){
        return Color::paginate($perPage);
    }

    public function create(ColorData $data) : Color{
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color = Color::create($cleanData);

        $color->refresh();

        return $color;
    }

    public function update(Color $color, ColorData $data) : Color{
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color->update($cleanData);

        return $color;
    }

    public function delete(Color $color) : bool {
        return $color->delete();
    }
}