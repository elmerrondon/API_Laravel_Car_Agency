<?php

namespace App\Http\Controllers\Sales;

use App\DTOs\Sales\PaymentMethodData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\PaymentMethod\IndexPaymentMethodRequest;
use App\Http\Requests\Sales\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\Sales\PaymentMethod\UpdatePaymentMethodRequest;
use App\Http\Resources\Sales\PaymentMethodResource;
use App\Models\Sales\PaymentMethod;
use App\Services\Sales\PaymentMethodService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentMethodService $service)
    {
        
    }

    public function index(IndexPaymentMethodRequest $request){
        $perPage = $request->validated('per_page');
        
        $paymentMethods = $this->service->getAllPaginatedPaymentMethods($perPage);

        return PaymentMethodResource::collection($paymentMethods);
    }

    public function show(PaymentMethod $paymentMethod){
        return new PaymentMethodResource($paymentMethod);
    }

    public function store(StorePaymentMethodRequest $request){
        $dto = PaymentMethodData::fromRequest($request);

        $paymentMethod = $this->service->createPaymentMethod($dto);

        return new PaymentMethodResource($paymentMethod);
    }

    public function update(PaymentMethod $paymentMethod, UpdatePaymentMethodRequest $request){
        $dto = PaymentMethodData::fromRequest($request);
        
        $paymentMethod = $this->service->updatePaymentMethod($paymentMethod, $dto);

        return new PaymentMethodResource($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod){
        $this->service->deletePaymentMethod($paymentMethod);

        return response()->noContent();
    }
}
