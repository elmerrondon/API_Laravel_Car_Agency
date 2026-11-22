<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\PaymentMethodData;
use App\Enums\Audit\AuditAction;
use App\Models\Sales\PaymentMethod;
use App\Services\Audit\AuditService;
use App\Support\ListFilters;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{is_active: bool|null, search: string|null}  $filters
     * @return LengthAwarePaginator<int, PaymentMethod>
     */
    public function getAllPaginatedPaymentMethods(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $query = PaymentMethod::select(['id', 'name', 'is_active']);

        if ($filters['is_active'] !== null) {
            $query->where('is_active', $filters['is_active']);
        }

        ListFilters::search($query, 'name', $filters['search']);

        return $query->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createPaymentMethod(PaymentMethodData $data): PaymentMethod
    {
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanArray = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $paymentMethod = PaymentMethod::create($cleanArray);

        $paymentMethod->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $paymentMethod, [], $this->paymentMethodAuditValues($paymentMethod));

        return $paymentMethod;
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, PaymentMethodData $data): PaymentMethod
    {
        $oldValues = $this->paymentMethodAuditValues($paymentMethod);

        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanArray = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $paymentMethod->update($cleanArray);
        $paymentMethod->refresh();

        $newValues = $this->paymentMethodAuditValues($paymentMethod);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $paymentMethod, $oldValues, $newValues);
        }

        return $paymentMethod;
    }

    public function deletePaymentMethod(PaymentMethod $paymentMethod): bool
    {
        $oldValues = $this->paymentMethodAuditValues($paymentMethod);

        $deleted = $paymentMethod->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $paymentMethod, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentMethodAuditValues(PaymentMethod $paymentMethod): array
    {
        return [
            'name' => (string) $paymentMethod->name,
            'is_active' => (bool) $paymentMethod->is_active,
        ];
    }
}
