<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\BranchData;
use App\Models\Locations\Branch;

class BranchService{
    public function getAllPaginated(int $perPage = 15){
        return Branch::paginate($perPage);
    }

    public function create(BranchData $data) : Branch{
        $nextNumber = Branch::count() + 1;
        $generatedCode = 'SUC-' . str_pad($nextNumber,4,'0',STR_PAD_LEFT);

        $dataArray = [
            'name' => $data->name, 
            'code' => $generatedCode, 
            'address' => $data->address, 
            'description' => $data->description, 
            'zip_code' => $data->zipCode, 
            'is_active' => $data->isActive, 
            'city_id' => $data->city_id
        ];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $branch =  Branch::create($cleanData);

        $branch->refresh();

        return $branch;
    }

    public function update(Branch $branch, BranchData $data) : Branch{
        $dataArray = [
            'name' => $data->name, 
            'address' => $data->address, 
            'description' => $data->description, 
            'zip_code' => $data->zipCode, 
            'is_active' => $data->isActive, 
            'city_id' => $data->city_id
        ];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $branch->update($cleanData);

        return $branch;
    }

    public function delete(Branch $branch) : bool{
        return $branch->delete();
    }
}