<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Las estadísticas filtran por sucursal, estado y rango de fechas.
            // Con `branch_id` al frente, las consultas sin sucursal siguen
            // usando `(status, sale_date)`; con sucursal, EXPLAIN resuelve
            // estado y fecha dentro del índice (filtered 100 %) sin leer las
            // ventas canceladas.
            $table->index(['branch_id', 'status', 'sale_date']);
        });

        Schema::table('cars', function (Blueprint $table) {
            // El inventario por sucursal agrupa por estado. Con el índice el
            // plan evita la tabla temporal del GROUP BY.
            $table->index(['branch_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'status', 'sale_date']);
        });
    }
};
