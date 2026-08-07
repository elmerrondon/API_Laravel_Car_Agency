<?php 

namespace App\Services\Sales;

use App\DTOs\Sales\SaleData;
use App\Enums\Cars\CarStatus;
use App\Enums\Sales\SaleStatus;
use App\Models\Cars\Car;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use App\Models\Sales\Tax;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaleService{

    public function getAllPaginatedSales(?int $perPage = null){
        return Sale::with(['car','user','branch','baseCurrency','paymentCurrency','paymentMethod'])->paginate($perPage);
    }

    public function createSale(SaleData $data){

        return DB::transaction(function () use ($data) {
            $car = Car::findOrFail($data->carId);
        
            if($car->status === CarStatus::SOLD){
                throw new LogicException('The car cannot be sold because it is already marked as sold');
            }

            if($car->status !== CarStatus::AVAILABLE){
                throw new LogicException('The car is not available for sale');
            }

            $basePrice = $car->price;
            $discountPercentage = $data->discountPercentage ?? 0;
            $totalDiscount = round($basePrice * ($discountPercentage / 100), 2);
            $taxableAmount = $basePrice - $totalDiscount;

            $taxesFromDb = Tax::whereIn('id', $data->taxes)->get();
            $totalTaxes = 0;
            $taxDetails = [];

            foreach($taxesFromDb as $tax){
                $calculatedTaxAmount = round($taxableAmount * ($tax->percentage / 100), 2);
                $totalTaxes += $calculatedTaxAmount;

                $taxDetails[] = [
                    'id' => $tax->id,
                    'name' => $tax->name,
                    'percentage' => $tax->percentage,
                    'amount_applied' => $calculatedTaxAmount
                ];
            }

            $totalBaseAmount = $taxableAmount + $totalTaxes;
            $totalAmountPaid = round($totalBaseAmount * $data->exchangeRate, 2);

            $baseCurrency = Currency::firstWhere('is_base',true);
            
            $sale = Sale::create(
                [
                    'exchange_rate' => $data->exchangeRate,
                    'base_price' => $basePrice,
                    'taxes' => $taxDetails,
                    'total_taxes' => $totalTaxes,
                    'discount_percentage' => $discountPercentage,
                    'total_base_amount' => $totalBaseAmount,
                    'total_amount_paid' => $totalAmountPaid,
                    'total_discount' => $totalDiscount,
                    'status' => SaleStatus::COMPLETED,
                    'sale_date' => $data->saleDate,
                    'car_id' => $data->carId,
                    'user_id' => $data->userId,
                    'branch_id' => $data->branchId,
                    'payment_currency_id' => $data->paymentCurrencyId,
                    'base_currency_id' => $baseCurrency->id,
                    'payment_method_id' => $data->paymentMethodId,
                    'notes' => $data->notes
                    ]);

                    $car->update(['status' => CarStatus::SOLD]);

                    return $sale->load(['car','user','branch','baseCurrency','paymentCurrency','paymentMethod']);

        });
    
    }

    public function cancelSale(Sale $sale, string $notes){
        return DB::transaction(function () use($sale, $notes){
            $car = $sale->car;

            $sale->status = SaleStatus::CANCELLED;
            $sale->notes = $notes;
            $car->status = CarStatus::AVAILABLE;

           
            $sale->save();
            $car->save();

            return $sale->load(['car','user','branch','baseCurrency','paymentCurrency','paymentMethod']);
        });
    }
}