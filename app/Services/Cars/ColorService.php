<?php

declare(strict_types=1);

namespace App\Services\Cars;

use App\DTOs\Cars\ColorData;
use App\Enums\Audit\AuditAction;
use App\Models\Cars\Color;
use App\Services\Audit\AuditService;
use Illuminate\Pagination\LengthAwarePaginator;

class ColorService
{
    public function __construct(private readonly AuditService $audit) {}

    /** @return LengthAwarePaginator<int, Color> */
    public function getAllPaginated(?int $perPage = null): LengthAwarePaginator
    {
        return Color::select(['id', 'name', 'is_active'])
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function createColor(ColorData $data): Color
    {
        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color = Color::create($cleanData);

        $color->refresh();

        $this->audit->record(AuditAction::CATALOG_CREATED, $color, [], $this->colorAuditValues($color));

        return $color;
    }

    public function updateColor(Color $color, ColorData $data): Color
    {
        $oldValues = $this->colorAuditValues($color);

        $arrayData = ['name' => $data->name, 'is_active' => $data->isActive];

        $cleanData = array_filter($arrayData, function ($value) {
            return $value !== null;
        });

        $color->update($cleanData);
        $color->refresh();

        $newValues = $this->colorAuditValues($color);

        if ($oldValues !== $newValues) {
            $this->audit->record(AuditAction::CATALOG_UPDATED, $color, $oldValues, $newValues);
        }

        return $color;
    }

    public function deleteColor(Color $color): bool
    {
        $oldValues = $this->colorAuditValues($color);

        $deleted = $color->delete();

        $this->audit->record(AuditAction::CATALOG_DELETED, $color, $oldValues, []);

        return (bool) $deleted;
    }

    /**
     * @return array<string, mixed>
     */
    private function colorAuditValues(Color $color): array
    {
        return [
            'name' => (string) $color->name,
            'is_active' => (bool) $color->is_active,
        ];
    }
}
