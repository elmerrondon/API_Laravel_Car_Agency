<?php

use App\Enums\Cars\CarStatus;
use App\Enums\Users\RoleEnum;
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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->decimal("price",15,2);
            $table->unsignedInteger("mileage");
            $table->unsignedSmallInteger("year");
            $table->string("vin",17)->unique();
            $table->string("status",20)->default(CarStatus::AVAILABLE->value);
            $table->foreignId("color_id")->constrained()->onDelete("restrict");
            $table->foreignId("car_model_id")->constrained()->onDelete("restrict");
            $table->foreignId("car_type_id")->constrained()->onDelete("restrict");
            $table->foreignId("category_id")->constrained()->onDelete("restrict");
            $table->foreignId("branch_id")->constrained()->onDelete("restrict");
            $table->softDeletes();
            $table->timestamps();
            $table->unique("vin","deleted_at");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
