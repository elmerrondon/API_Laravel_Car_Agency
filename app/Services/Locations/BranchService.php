<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\BranchData;
use App\Models\Locations\Branch;

class BranchService
{
    private const RELATIONS = [
        'city:id,name,state_id,is_active',
        'city.state:id,name,country_id,is_active',
        'city.state.country:id,name,is_active',
    ];

    public function getAllPaginated(?int $perPage = null)
    {
        return Branch::with(self::RELATIONS)
            ->select(['id', 'name', 'code', 'address', 'description', 'zip_code', 'is_active', 'city_id'])
            ->paginate($perPage);
    }

    public function getBranch(Branch $branch): Branch
    {
        return $branch->load(self::RELATIONS);
    }

    public function createBranch(BranchData $data): Branch
    {

        $maxId = (int) Branch::max('id');
        $nextNumber = $maxId + 1;
        $generatedCode = 'SUC-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);

        $dataArray = [
            'name' => $data->name,
            'code' => $generatedCode,
            'address' => $data->address,
            'description' => $data->description,
            'zip_code' => $data->zipCode,
            'is_active' => $data->isActive,
            'city_id' => $data->city_id,
        ];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $branch = Branch::create($cleanData);

        $branch->refresh();

        return $this->getBranch($branch);
    }

    public function updateBranch(Branch $branch, BranchData $data): Branch
    {
        $dataArray = [
            'name' => $data->name,
            'address' => $data->address,
            'description' => $data->description,
            'zip_code' => $data->zipCode,
            'is_active' => $data->isActive,
            'city_id' => $data->city_id,
        ];

        $cleanData = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $branch->update($cleanData);

        return $this->getBranch($branch);
    }

    public function deleteBranch(Branch $branch): bool
    {
        return $branch->delete();
    }
}
