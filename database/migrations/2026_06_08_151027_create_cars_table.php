<?php

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
            $table->decimal("price",10,2);
            $table->decimal("mileage",8,2);
            $table->unsignedSmallInteger("year");
            $table->string("vin",17)->unique();
            $table->string("status",20);
            $table->foreignId("color_id")->constrained()->onDelete("restrict");
            $table->foreignId("car_model_id")->constrained()->onDelete("restrict");
            $table->foreignId("car_type_id")->constrained()->onDelete("restrict");
            $table->foreignId("category_id")->constrained()->onDelete("restrict");
            $table->foreignId("branch_id")->constrained()->onDelete("restrict");
            $table->softDeletes();
            $table->timestamps();
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
