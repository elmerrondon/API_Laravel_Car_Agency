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
    public function __construct(private readonly BranchService $service){

    }

    public function index(IndexBranchRequest $request){
        $perPage = $request->validated('per_page');
        $branches = $this->service->getAllPaginated($perPage);

        return BranchResource::collection($branches);
    }

    public function show(Branch $branch){
        $branch->load('city');
        return new BranchResource($branch); 
    }

    public function store(StoreBranchRequest $request){
        $dto = BranchData::fromRequest($request);

        $branch = $this->service->createBranch($dto);

        return new BranchResource($branch);
    }

    public function update(Branch $branch, UpdateBranchRequest $request){
        $dto = BranchData::fromRequest($request);

        $branch = $this->service->updateBranch($branch,$dto);

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch){
        $this->service->deleteBranch($branch);

        return response()->noContent();
    }
}
