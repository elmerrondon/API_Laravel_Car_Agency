<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Unicidad sin distinguir mayúsculas y minúsculas.
 *
 * El cotejo de PostgreSQL es binario, así que un `unique` plano aceptaría
 * `Sedán` y `sedán` como valores distintos. Esta regla compara contra
 * `lower(columna)`, que es además la expresión de los índices únicos de los
 * catálogos, y deja que la base sea la última defensa ante una carrera.
 */
final class CaseInsensitiveUnique implements ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $conditions = [];

    private bool $withoutTrashed = false;

    public function __construct(
        private readonly string $table,
        private readonly string $column,
        private readonly int|string|null $ignoreId = null,
        private readonly string $ignoreColumn = 'id',
    ) {}

    /**
     * Añade una condición extra a la consulta (por ejemplo `country_id` en
     * los estados o `state_id` en las ciudades).
     */
    public function where(string $column, mixed $value): self
    {
        $this->conditions[$column] = $value;

        return $this;
    }

    /**
     * Ignora las filas con borrado lógico, para que el valor pueda reutilizarse
     * cuando el registro original ya no está activo.
     */
    public function withoutTrashed(): self
    {
        $this->withoutTrashed = true;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->exists($value)) {
            $fail('validation.unique')->translate();
        }
    }

    private function exists(string $value): bool
    {
        $query = DB::table($this->table)
            ->whereRaw(sprintf('lower(%s) = ?', $this->column), [mb_strtolower($value)]);

        foreach ($this->conditions as $column => $condition) {
            $query->where($column, $condition);
        }

        if ($this->withoutTrashed) {
            $query->whereNull('deleted_at');
        }

        if ($this->ignoreId !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignoreId);
        }

        return $query->exists();
    }
}
