<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\DTOs\Sales\CurrencyData;
use App\Enums\Audit\AuditAction;
use App\Exceptions\Sales\BaseCurrencyAlreadyExistsException;
use App\Models\Sales\Currency;
use App\Models\Sales\Sale;
use App\Services\Audit\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use LogicException;

class CurrencyService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return LengthAwarePaginator<int, Currency> */
    public function getAllPaginatedCurrencies(?int $perPage = null): LengthAwarePaginator
    {
        return Currency::select(['id', 'name', 'code', 'is_base', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createCurrency(CurrencyData $data): Currency
    {
        $dataArray = ['name' => $data->name, 'code' => $data->code, 'is_base' => $data->isBase, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $isBase = $cleanArray['is_base'] ?? false;

        if (($isBase && Currency::where('is_base', true)->exists())) {
            throw new LogicException('The base currency cannot be created because a base currency already exists');
        }

        try {
            $currency = Currency::create($cleanArray);
        } catch (QueryException $e) {
            if ($this->isSingleBaseViolation($e)) {
                throw BaseCurrencyAlreadyExistsException::create();
            }

            throw $e;
        }

        $currency->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $currency, [], $this->currencyAuditValues($currency));

        return $currency;
    }

    public function updateCurrency(Currency $currency, CurrencyData $data): Currency
    {
        $oldValues = $this->currencyAuditValues($currency);

        $dataArray = ['name' => $data->name, 'code' => $data->code, 'is_base' => $data->isBase, 'is_active' => $data->isActive];

        $cleanArray = array_filter($dataArray, function ($value) {
            return $value !== null;
        });

        $isBase = $cleanArray['is_base'] ?? false;
        $existsBase = Currency::where('is_base', true)->where('id', '!=', $currency->id)->exists();

        if ($currency->is_base && array_key_exists('is_base', $cleanArray) && $cleanArray['is_base'] === false) {
            throw new LogicException('The base currency cannot be unset because the system needs a base currency');
        }

        if (($isBase && Sale::exists())) {
            throw new LogicException('The currency base cannot be updated because sales already exists in the system');
        }

        if (($existsBase && $isBase)) {
            throw new LogicException('The base currency cannot be updated because a base currency already exists');
        }

        try {
            $currency->update($cleanArray);
        } catch (QueryException $e) {
            if ($this->isSingleBaseViolation($e)) {
                throw BaseCurrencyAlreadyExistsException::update();
            }

            throw $e;
        }

        $currency->refresh();

        $newValues = $this->currencyAuditValues($currency);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $currency, $oldValues, $newValues);
        }

        return $currency;
    }

    public function deleteCurrency(Currency $currency): bool
    {
        if ($currency->is_base && Sale::exists()) {
            throw new LogicException('The currency base cannot be deleted because sales already exists in the system');
        }
        if ($currency->is_base === true) {
            throw new LogicException('The currency cannot be deleted because it is the system base currency, change the base currency to delete it');
        }

        $oldValues = $this->currencyAuditValues($currency);

        $deleted = $currency->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $currency, $oldValues, []);

        return (bool) $deleted;
    }

    private function isSingleBaseViolation(QueryException $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        return ($e->getCode() === '23505' && str_contains($message, 'currencies_single_base_unique'))
            || ($e->getCode() === '23000' && str_contains($message, 'unique constraint failed: currencies.is_base'));
    }

    /**
     * @return array<string, mixed>
     */
    private function currencyAuditValues(Currency $currency): array
    {
        return [
            'name' => (string) $currency->name,
            'code' => (string) $currency->code,
            'is_base' => (bool) $currency->is_base,
            'is_active' => (bool) $currency->is_active,
        ];
    }
}
