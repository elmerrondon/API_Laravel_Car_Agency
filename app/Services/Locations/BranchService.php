<?php 

namespace App\Services\Locations;

use App\DTOs\Locations\BranchData;
use App\Models\Locations\Branch;

class BranchService{
    public function getAllPaginated(?int $perPage = null){
        return Branch::with('city')->paginate($perPage);
    }

    public function createBranch(BranchData $data) : Branch{

        $maxId = (int) Branch::max('id');
        $nextNumber = $maxId + 1;
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

        $branch->load('city');

        return $branch;
    }

    public function updateBranch(Branch $branch, BranchData $data) : Branch{
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

        $branch->load('city');
        
        return $branch;
    }

    public function deleteBranch(Branch $branch) : bool{
        return $branch->delete();
    }
}