<?php 

namespace App\Services\Sales;

use App\DTOs\Sales\CurrencyData;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use InvalidArgumentException;
use LogicException;

class CurrencyService {
    public function getAllPaginatedCurrecies(?int $perPage = null){
        return Currency::paginate($perPage);
    }

    public function createCurrency(CurrencyData $data){
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'is_base' => $data->isBase, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $isBase = $cleanArray['is_base'] ?? false;

        if(($isBase && Currency::where('is_base',true)->exists())){
            throw new LogicException('The base currency cannot be created because a base currency already exists');
        }

        $currency = Currency::create($cleanArray);

        $currency->refresh();

        return $currency;
    }

    public function updateCurrency(Currency $currency, CurrencyData $data){
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'is_base' => $data->isBase, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $isBase = $cleanArray['is_base'] ?? false;
        $existsBase = Currency::where('is_base', true)->where('id', '!=', $currency->id)->exists();

        if(($isBase && Sale::exists())){
            throw new LogicException('The currency base cannot be updated because sales already exists in the system');
        }

        if(($existsBase && $isBase)){
            throw new LogicException('The base currency cannot be updated because a base currency already exists');
        }

        $currency->update($cleanArray);

        return $currency;
    }

    public function deleteCurrency(Currency $currency) {
        if($currency->is_base && Sale::exists()){
           throw new LogicException('The currency base cannot be deleted because sales already exists in the system');
        }
        if($currency->is_base === true){
            throw new LogicException('The currency cannot be deleted because it is the system base currency, change the base currency to delete it');
        }

        return $currency->delete();
    }
}