<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\TaxData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Tax\IndexTaxRequest;
use App\Http\Requests\Sales\Tax\StoreTaxRequest;
use App\Http\Requests\Sales\Tax\UpdateTaxRequest;
use App\Http\Resources\Sales\TaxResource;
use App\Models\Sales\Tax;
use App\Services\Sales\TaxService;

class TaxController extends Controller
{
    public function __construct(private readonly TaxService $service) {}

    public function index(IndexTaxRequest $request)
    {
        $perPage = $this->perPage($request);

        $taxes = $this->service->getAllPaginatedTaxes($perPage);

        return TaxResource::collection($taxes);
    }

    public function show(Tax $tax)
    {
        return new TaxResource($tax);
    }

    public function store(StoreTaxRequest $request)
    {
        $dto = TaxData::fromRequest($request);

        $tax = $this->service->createTax($dto);

        return new TaxResource($tax);
    }

    public function update(Tax $tax, UpdateTaxRequest $request)
    {
        $dto = TaxData::fromRequest($request);

        $taxUpdated = $this->service->updateTax($tax, $dto);

        return new TaxResource($taxUpdated);
    }

    public function destroy(Tax $tax)
    {
        $this->service->deleteTax($tax);

        return response()->noContent();
    }
}
