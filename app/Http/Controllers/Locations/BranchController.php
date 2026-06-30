<?php

namespace App\Http\Controllers\Locations;

use App\DTOs\Locations\BranchData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\Branch\IndexBranchRequest;
use App\Http\Requests\Locations\Branch\StoreBranchRequest;
use App\Http\Requests\Locations\Branch\UpdateBranchRequest;
use App\Http\Resources\Locations\BranchResource;
use App\Models\Locations\Branch;
use App\Services\Locations\BranchService;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(IndexBranchRequest $request, BranchService $service){
        $perPage = $request->validated('per_page');
        $branches = $service->getAllPaginated($perPage);

        return BranchResource::collection($branches);
    }

    public function show(Branch $branch){
        return new BranchResource($branch); 
    }

    public function store(StoreBranchRequest $request, BranchService $service){
        $dto = BranchData::fromRequest($request);

        $branch = $service->create($dto);

        return new BranchResource($branch);
    }

    public function update(Branch $branch, UpdateBranchRequest $request, BranchService $service){
        $dto = BranchData::fromRequest($request);

        $branch = $service->update($branch,$dto);

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch, BranchService $service){
        $service->delete($branch);

        return response()->noContent();
    }
}
