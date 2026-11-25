<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para los filtros de listado añadidos en la fase 6.
     *
     * Los catálogos ya tienen `lower(name)` único y `sales` ya cubre estado,
     * sucursal, fecha y todas sus claves foráneas. Faltaban las columnas de
     * búsqueda de usuarios (email y documento ya son parciales, pero el
     * prefijo por nombre y apellido no tenía índice) y los rangos de coches.
     */
    public function up(): void
    {
        DB::statement('create index users_name_lower_index on users (lower(name))');
        DB::statement('create index users_last_name_lower_index on users (lower(last_name))');
        DB::statement('create index users_code_lower_index on users (lower(code))');

        Schema::table('cars', function (Blueprint $table) {
            $table->index('year');
            $table->index('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['year']);
            $table->dropIndex(['price']);
        });

        DB::statement('drop index if exists users_code_lower_index');
        DB::statement('drop index if exists users_last_name_lower_index');
        DB::statement('drop index if exists users_name_lower_index');
    }
};
