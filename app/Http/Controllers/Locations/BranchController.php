<?php

declare(strict_types=1);

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\Branch\IndexBranchRequest;
use App\Http\Requests\Locations\Branch\StoreBranchRequest;
use App\Http\Requests\Locations\Branch\UpdateBranchRequest;
use App\Http\Resources\Locations\BranchResource;
use App\Models\Locations\Branch;
use App\Services\Locations\BranchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    public function __construct(private readonly BranchService $service) {}

    public function index(IndexBranchRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $branches = $this->service->getAllPaginated($this->filters($request), $perPage);

        return BranchResource::collection($branches);
    }

    /**
     * @return array{city_id: int|null, is_active: bool|null, search: string|null}
     */
    private function filters(IndexBranchRequest $request): array
    {
        return [
            'city_id' => $this->nullableInt($request, 'city_id'),
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($this->service->getBranch($branch));
    }

    public function store(StoreBranchRequest $request): BranchResource
    {
        $dto = BranchData::fromRequest($request);

        $branch = $this->service->createBranch($dto);

        return new BranchResource($branch);
    }

    public function update(Branch $branch, UpdateBranchRequest $request): BranchResource
    {
        $dto = BranchData::fromRequest($request);

        $branch = $this->service->updateBranch($branch, $dto);

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): Response
    {
        $this->service->deleteBranch($branch);

        return response()->noContent();
    }
}
