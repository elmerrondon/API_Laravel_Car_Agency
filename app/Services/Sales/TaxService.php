<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\TaxData;
use App\Enums\Audit\AuditAction;
use App\Models\Sales\Tax;
use App\Services\Audit\AuditService;

class TaxService
{
    public function __construct(private readonly AuditService $audit) {}

    public function getAllPaginatedTaxes(?int $perPage = null)
    {
        return Tax::select(['id', 'name', 'code', 'percentage', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createTax(TaxData $data): Tax
    {
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'percentage' => $data->percentage, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $taxe = Tax::create($cleanArray);

        $taxe->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $taxe, [], $this->taxAuditValues($taxe));

        return $taxe;
    }

    public function updateTax(Tax $taxe, TaxData $data): Tax
    {
        $oldValues = $this->taxAuditValues($taxe);

        $dataArray = ['name' => $data->name, 'code' => $data->code, 'percentage' => $data->percentage, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $taxe->update($cleanArray);
        $taxe->refresh();

        $newValues = $this->taxAuditValues($taxe);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $taxe, $oldValues, $newValues);
        }

        return $taxe;
    }

    public function deleteTax(Tax $taxe): bool
    {
        $oldValues = $this->taxAuditValues($taxe);

        $deleted = $taxe->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $taxe, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function taxAuditValues(Tax $taxe): array
    {
        return [
            'name' => (string) $taxe->name,
            'code' => (string) $taxe->code,
            'percentage' => (float) $taxe->percentage,
            'is_active' => (bool) $taxe->is_active,
        ];
    }
}
