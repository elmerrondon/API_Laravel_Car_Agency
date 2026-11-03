<?php

declare(strict_types=1);

namespace App\Services\Locations;

use App\DTOs\Locations\BranchData;
use App\Enums\Audit\AuditAction;
use App\Models\Locations\Branch;
use App\Services\Audit\AuditService;

class BranchService
{
    private const RELATIONS = [
        'city:id,name,state_id,is_active',
        'city.state:id,name,country_id,is_active',
        'city.state.country:id,name,is_active',
    ];

    public function __construct(private readonly AuditService $audit) {}

    public function getAllPaginated(?int $perPage = null)
    {
        return Branch::with(self::RELATIONS)
            ->select(['id', 'name', 'code', 'address', 'description', 'zip_code', 'is_active', 'city_id'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
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

        $branch = $this->getBranch($branch);

        $this->audit->record(AuditAction::BRANCH_CREATED, $branch, [], $this->branchAuditValues($branch));

        return $branch;
    }

    public function updateBranch(Branch $branch, BranchData $data): Branch
    {
        $oldValues = $this->branchAuditValues($branch);

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

        $branch = $this->getBranch($branch);

        $this->audit->record(AuditAction::BRANCH_UPDATED, $branch, $oldValues, $this->branchAuditValues($branch));

        return $branch;
    }

    public function deleteBranch(Branch $branch): bool
    {
        $oldValues = $this->branchAuditValues($branch);

        $deleted = $branch->delete();

        $this->audit->record(AuditAction::BRANCH_DELETED, $branch, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function branchAuditValues(Branch $branch): array
    {
        return [
            'name' => (string) $branch->name,
            'code' => (string) $branch->code,
            'address' => (string) $branch->address,
            'description' => $branch->description === null ? null : (string) $branch->description,
            'zip_code' => (string) $branch->zip_code,
            'is_active' => (bool) $branch->is_active,
            'city_id' => (int) $branch->city_id,
        ];
    }
}
