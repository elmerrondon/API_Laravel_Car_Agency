<?php

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\CurrencyData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Currency\IndexCurrencyRequest;
use App\Http\Requests\Sales\Currency\StoreCurrencyRequest;
use App\Http\Requests\Sales\Currency\UpdateCurrencyRequest;
use App\Http\Resources\Sales\CurrencyResource;
use App\Models\Sales\Currency;
use App\Services\Sales\CurrencyService;
use Illuminate\Http\Request;
use LogicException;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $service)
    {
    
    }

    public function index(IndexCurrencyRequest $request){
        $perPage = $request->validated('per_page');

        $currencies = $this->service->getAllPaginatedCurrecies($perPage);

        return CurrencyResource::collection($currencies);
    }

    public function show(Currency $currency){
        return new CurrencyResource($currency);
    }

    public function store(StoreCurrencyRequest $request){
        try {
            $dto = CurrencyData::fromRequest($request);

            $currency = $this->service->createCurrency($dto);

            return new CurrencyResource($currency);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } 
    }

    public function update(Currency $currency, UpdateCurrencyRequest $request){
        try {
            $dto = CurrencyData::fromRequest($request);

            $currency = $this->service->updateCurrency($currency, $dto);

            return new CurrencyResource($currency);

        } catch(LogicException $e){
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function destroy(Currency $currency){
        try{
            $this->service->deleteCurrency($currency);

            return response()->noContent();
            
        } catch (LogicException $e){
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
