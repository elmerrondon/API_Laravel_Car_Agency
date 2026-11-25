<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Garantiza en la base de datos que solo pueda existir una moneda base.
     *
     * La validación de los FormRequest y del servicio es amable, pero sigue
     * siendo TOCTOU: dos peticiones concurrentes pueden pasarla a la vez. El
     * índice único parcial es la última línea de defensa y funciona igual en
     * PostgreSQL y en SQLite.
     */
    public function up(): void
    {
        $duplicates = (int) DB::table('currencies')
            ->where('is_base', true)
            ->count();

        if ($duplicates > 1) {
            throw new RuntimeException(
                sprintf('No se puede crear el índice de moneda base: hay %d monedas base.', $duplicates),
            );
        }

        DB::statement('create unique index currencies_single_base_unique on currencies (is_base) where is_base');
    }

    public function down(): void
    {
        DB::statement('drop index if exists currencies_single_base_unique');
    }
};
