<?php

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\TaxData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Tax\IndexTaxRequest;
use App\Http\Requests\Sales\Tax\StoreTaxRequest;
use App\Http\Requests\Sales\Tax\UpdateTaxRequest;
use App\Http\Resources\Sales\TaxResource;
use App\Models\Sales\Tax;
use App\Services\Sales\TaxService;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    public function __construct(private readonly TaxService $service)
    {
        
    }

    public function index(IndexTaxRequest $request){
        $perPage = $request->validated('per_page');

        $Taxs = $this->service->getAllPaginatedTaxes($perPage);

        return TaxResource::collection($Taxs);
    }

    public function show(Tax $tax){
        return new TaxResource($tax);
    }

    public function store(StoreTaxRequest $request){
        $dto = TaxData::fromRequest($request);

        $Tax = $this->service->createTax($dto);

        return new TaxResource($Tax);
    }

    public function update(Tax $Tax, UpdateTaxRequest $request){
        $dto = TaxData::fromRequest($request);

        $TaxUpdated = $this->service->updateTax($Tax, $dto);

        return new TaxResource($TaxUpdated);
    }

    public function destroy(Tax $Tax){
        $this->service->deleteTax($Tax);

        return response()->noContent();
    }
}
