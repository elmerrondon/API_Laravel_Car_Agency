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
        Schema::table('cars', function (Blueprint $table) {
            $table->index('status');

            // branch_id queda cubierto por el índice (branch_id, status) y
            // car_model_id por el de claves foráneas; aquí no se repiten.
            // El índice único "deleted_at" sobre vin duplica cars_vin_unique:
            // vin conserva unicidad global, así que no aporta nada.
            $table->dropUnique('deleted_at');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('sale_date');
            $table->index(['status', 'sale_date']);

            // user_id tiene su propio índice (migración de claves foráneas).
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['status', 'sale_date']);
            $table->dropIndex(['sale_date']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->unique('vin', 'deleted_at');
        });
    }
};
