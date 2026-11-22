<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cars;

use App\DTOs\Cars\ColorData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cars\Color\IndexColorRequest;
use App\Http\Requests\Cars\Color\StoreColorRequest;
use App\Http\Requests\Cars\Color\UpdateColorRequest;
use App\Http\Resources\Cars\ColorResource;
use App\Models\Cars\Color;
use App\Services\Cars\ColorService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ColorController extends Controller
{
    public function __construct(private readonly ColorService $service) {}

    public function index(IndexColorRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $colors = $this->service->getAllPaginated($this->filters($request), $perPage);

        return ColorResource::collection($colors);
    }

    /**
     * @return array{is_active: bool|null, search: string|null}
     */
    private function filters(IndexColorRequest $request): array
    {
        return [
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(Color $color): ColorResource
    {
        return new ColorResource($color);
    }

    public function store(StoreColorRequest $request): ColorResource
    {
        $dto = ColorData::fromRequest($request);

        $color = $this->service->createColor($dto);

        return new ColorResource($color);
    }

    public function update(Color $color, UpdateColorRequest $request): ColorResource
    {
        $dto = ColorData::fromRequest($request);

        $color = $this->service->updateColor($color, $dto);

        return new ColorResource($color);
    }

    public function destroy(Color $color): Response
    {
        $this->service->deleteColor($color);

        return response()->noContent();
    }
}
