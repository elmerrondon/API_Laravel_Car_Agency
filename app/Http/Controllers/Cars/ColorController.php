<?php

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\ColorData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Color\IndexColorRequest;
use App\Http\Requests\Cars\Color\StoreColorRequest;
use App\Http\Requests\Cars\Color\UpdateColorRequest;
use App\Http\Resources\Cars\ColorResource;
use App\Models\Cars\Color;
use App\Services\Cars\ColorService;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    public function index(IndexColorRequest $request, ColorService $service){
        $perPage = $request->validated('per_page');

        $colors = $service->getAllPaginated($perPage);

        return ColorResource::collection($colors);
    }

    public function show(Color $color){
        return new ColorResource($color);
    }

    public function store(StoreColorRequest $request, ColorService $service){
        $dto = ColorData::fromData($request);

        $color = $service->create($dto);

        return new ColorResource($color);
    }

    public function update(Color $color, UpdateColorRequest $request, ColorService $service){
        $dto = ColorData::fromData($request);

        $color = $service->update($color, $dto);

        return new ColorResource($color);
    }

    public function destroy(Color $color, ColorService $service){
        $service->delete($color);

        return response()->noContent();
    }
}
