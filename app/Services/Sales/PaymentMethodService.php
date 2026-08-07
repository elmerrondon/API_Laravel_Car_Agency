<?php 

namespace App\Services\Sales;

use App\DTOs\Sales\PaymentMethodData;
use App\Models\Sales\PaymentMethod;

class PaymentMethodService {
    public function getAllPaginatedPaymentMethods(?int $perPage = null){
        return PaymentMethod::paginate($perPage);
    }

    public function createPaymentMethod(PaymentMethodData $data) : PaymentMethod{
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanArray = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $paymentMethod = PaymentMethod::create($cleanArray);

        $paymentMethod->refresh();

        return $paymentMethod;
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, PaymentMethodData $data) : PaymentMethod{
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanArray = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $paymentMethod->update($cleanArray);

        return $paymentMethod;
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod) : bool{
        return $paymentMethod->delete();
    }
}