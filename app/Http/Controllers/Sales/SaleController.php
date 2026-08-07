<?php

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\SaleData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Sale\CancelSaleRequest;
use App\Http\Requests\Sales\Sale\IndexSaleRequest;
use App\Http\Requests\Sales\Sale\StoreSaleRequest;
use App\Http\Resources\Sales\SaleResource;
use App\Models\Sales\Sale;
use App\Services\Sales\SaleService;
use LogicException;

class SaleController extends Controller
{
    public function __construct(private readonly SaleService $service)
    {
       
    }

    public function index(IndexSaleRequest $request){
        $perPage = $request->validated('per_page');
        $sales = $this->service->getAllPaginatedSales($perPage);

        return SaleResource::collection($sales);
    }

    public function show(Sale $sale){
        return new SaleResource($sale);
    }

    public function store(StoreSaleRequest $request){
        try{
            $dto = SaleData::fromRequest($request);
            $sale = $this->service->createSale($dto);
            return new SaleResource($sale);

        }catch(LogicException $e){
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function cancelSale(Sale $sale, CancelSaleRequest $request){
        $notes  = $request->validated('notes');
        $canceledSale = $this->service->cancelSale($sale, $notes);
        return new SaleResource($canceledSale);
    }
}
