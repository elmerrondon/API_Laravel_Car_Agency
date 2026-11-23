<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Búsqueda por prefijo compartida por los listados.
 *
 * `lower(columna) LIKE 'termino%'` aprovecha los índices btree de expresión
 * `lower(...)` que ya tienen catálogos y usuarios. No se ofrece búsqueda por
 * substring porque eso exigiría `pg_trgm` (una extensión de PostgreSQL) y no
 * puede garantizarse en la imagen de la PaaS.
 */
final class ListFilters
{
    /**
     * Valores admitidos por los filtros booleanos de la query string.
     *
     * Solo cadenas: la regla `boolean` de Laravel rechaza `"false"` y mezclar
     * booleanos con cadenas hace que Scramble documente un enum con valores
     * duplicados (`true` y `"1"` acaban como `"1"`).
     *
     * @var list<string>
     */
    public const BOOLEAN_VALUES = ['1', '0', 'true', 'false'];

    /**
     * Escapa los comodines de LIKE y añade el sufijo `%` del prefijo.
     */
    public static function prefix(string $term): string
    {
        $term = trim($term);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

        return mb_strtolower($escaped).'%';
    }

    /**
     * Filtra por prefijo sobre una columna. Los nombres de columna son
     * literales definidos en cada servicio, nunca datos de la petición.
     */
    public static function search(Builder $query, string $column, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $query->whereRaw(sprintf("lower(%s) like ? escape '!'", $column), [self::prefix($term)]);
    }

    /**
     * Igual que `search`, pero cualquiera de las columnas puede coincidir.
     *
     * @param  list<string>  $columns
     */
    public static function searchAny(Builder $query, array $columns, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $pattern = self::prefix($term);

        $query->where(function (Builder $nested) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $nested->orWhereRaw(sprintf("lower(%s) like ? escape '!'", $column), [$pattern]);
            }
        });
    }
}
