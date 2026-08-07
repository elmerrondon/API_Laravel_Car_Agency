<?php 

namespace App\Services\Sales;

use App\DTOs\Sales\TaxData;
use App\Models\Sales\Tax;

class TaxService{
    
    public function getAllPaginatedTaxes(?int $perPage = null){
        return Tax::paginate($perPage);
    }

    public function createTax(TaxData $data) : Tax{
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'percentage' => $data->percentage, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $taxe = Tax::create($cleanArray);

        $taxe->refresh();

        return $taxe;
    }

    public function updateTax(Tax $taxe, TaxData $data) : Tax{
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'percentage' => $data->percentage, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $taxe->update($cleanArray);

        return $taxe;
    }

    public function deleteTax(Tax $taxe) : bool{
        return $taxe->delete();
    }

}