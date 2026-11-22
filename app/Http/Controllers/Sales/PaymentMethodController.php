<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\PaymentMethodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\PaymentMethod\IndexPaymentMethodRequest;
use App\Http\Requests\Sales\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\Sales\PaymentMethod\UpdatePaymentMethodRequest;
use App\Http\Resources\Sales\PaymentMethodResource;
use App\Models\Sales\PaymentMethod;
use App\Services\Sales\PaymentMethodService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $service) {}

    public function index(IndexPaymentMethodRequest $request): AnonymousResourceCollection
    {
        $perPage = $this->perPage($request);

        $paymentMethods = $this->service->getAllPaginatedPaymentMethods($this->filters($request), $perPage);

        return PaymentMethodResource::collection($paymentMethods);
    }

    /**
     * @return array{is_active: bool|null, search: string|null}
     */
    private function filters(IndexPaymentMethodRequest $request): array
    {
        return [
            'is_active' => $this->nullableBool($request, 'is_active'),
            'search' => $request->validated('search'),
        ];
    }

    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        return new PaymentMethodResource($paymentMethod);
    }

    public function store(StorePaymentMethodRequest $request): PaymentMethodResource
    {
        $dto = PaymentMethodData::fromRequest($request);

        $paymentMethod = $this->service->createPaymentMethod($dto);

        return new PaymentMethodResource($paymentMethod);
    }

    public function update(PaymentMethod $paymentMethod, UpdatePaymentMethodRequest $request): PaymentMethodResource
    {
        $dto = PaymentMethodData::fromRequest($request);

        $paymentMethod = $this->service->updatePaymentMethod($paymentMethod, $dto);

        return new PaymentMethodResource($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): Response
    {
        $this->service->deletePaymentMethod($paymentMethod);

        return response()->noContent();
    }
}
