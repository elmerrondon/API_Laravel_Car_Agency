<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\SaleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Sale\CancelSaleRequest;
use App\Http\Requests\Sales\Sale\IndexSaleRequest;
use App\Http\Requests\Sales\Sale\StoreSaleRequest;
use App\Http\Resources\Sales\SaleResource;
use App\Models\Sales\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $service) {}

    public function index(IndexSaleRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);
        $sales = $this->service->getAllPaginatedSales($perPage);

        return SaleResource::collection($sales);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($this->service->getSale($sale));
    }

    public function store(StoreSaleRequest $request): SaleResource
    {
        $dto = SaleData::fromRequest($request);

        return new SaleResource($this->service->createSale($dto));
    }

    public function cancelSale(Sale $sale, CancelSaleRequest $request): SaleResource
    {
        $notes = (string) $request->validated('notes');
        $canceledSale = $this->service->cancelSale($sale, $notes);

        return new SaleResource($canceledSale);
    }
}
