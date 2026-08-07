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
    public function __construct(private readonly ColorService $service)
    {
        
    }

    public function index(IndexColorRequest $request){
        $perPage = $request->validated('per_page');

        $colors = $this->service->getAllPaginated($perPage);

        return ColorResource::collection($colors);
    }

    public function show(Color $color){
        return new ColorResource($color);
    }

    public function store(StoreColorRequest $request){
        $dto = ColorData::fromData($request);

        $color = $this->service->createColor($dto);

        return new ColorResource($color);
    }

    public function update(Color $color, UpdateColorRequest $request){
        $dto = ColorData::fromData($request);

        $color = $this->service->updateColor($color, $dto);

        return new ColorResource($color);
    }

    public function destroy(Color $color){
        $this->service->deleteColor($color);

        return response()->noContent();
    }
}
