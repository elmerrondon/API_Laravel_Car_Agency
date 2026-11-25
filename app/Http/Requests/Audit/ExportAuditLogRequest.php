<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reutiliza exactamente los filtros del listado, sin paginación: el CSV se
 * transmite completo en streaming.
 */
class ExportAuditLogRequest extends IndexAuditLogRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['per_page']);

        return $rules;
    }
}
