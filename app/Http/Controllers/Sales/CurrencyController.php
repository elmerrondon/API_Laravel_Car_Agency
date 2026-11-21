<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\CurrencyData;
use App\Exceptions\Sales\BaseCurrencyAlreadyExistsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\Currency\IndexCurrencyRequest;
use App\Http\Requests\Sales\Currency\StoreCurrencyRequest;
use App\Http\Requests\Sales\Currency\UpdateCurrencyRequest;
use App\Http\Resources\Sales\CurrencyResource;
use App\Models\Sales\Currency;
use App\Services\Sales\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use LogicException;

class CurrencyController extends Controller
{
    public function __construct(private readonly CurrencyService $service) {}

    public function index(IndexCurrencyRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $currencies = $this->service->getAllPaginatedCurrencies($perPage);

        return CurrencyResource::collection($currencies);
    }

    public function show(Currency $currency): CurrencyResource
    {
        return new CurrencyResource($currency);
    }

    public function store(StoreCurrencyRequest $request): CurrencyResource|JsonResponse
    {
        try {
            $dto = CurrencyData::fromRequest($request);

            $currency = $this->service->createCurrency($dto);

            return new CurrencyResource($currency);
        } catch (LogicException|BaseCurrencyAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function update(Currency $currency, UpdateCurrencyRequest $request): CurrencyResource|JsonResponse
    {
        try {
            $dto = CurrencyData::fromRequest($request);

            $currency = $this->service->updateCurrency($currency, $dto);

            return new CurrencyResource($currency);

        } catch (LogicException|BaseCurrencyAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function destroy(Currency $currency): Response|JsonResponse
    {
        try {
            $this->service->deleteCurrency($currency);

            return response()->noContent();

        } catch (LogicException|BaseCurrencyAlreadyExistsException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
