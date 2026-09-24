<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motorcycles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('plate')->index();
            $table->string('brand');
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('engine_number')->nullable();
            $table->string('chassis_number')->nullable();
            $table->string('vin')->nullable();
            $table->unsignedInteger('engine_displacement')->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->string('photo')->nullable();
            $table->string('vehicle_type')->default('motorcycle');
            $table->string('status')->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycles');
    }
};
