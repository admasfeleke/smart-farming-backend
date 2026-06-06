<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soil_health', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plot_id')->constrained('plots')->cascadeOnDelete();
            $table->decimal('ph_level', 4, 2)->nullable()->comment('Soil pH level');
            $table->decimal('nitrogen', 6, 2)->nullable()->comment('Nitrogen content in kg/ha');
            $table->decimal('phosphorus', 6, 2)->nullable()->comment('Phosphorus content in kg/ha');
            $table->decimal('potassium', 6, 2)->nullable()->comment('Potassium content in kg/ha');
            $table->decimal('organic_matter', 5, 2)->nullable()->comment('Organic matter percentage');
            $table->string('soil_type', 50)->nullable()->comment('Type of soil');
            $table->decimal('moisture_level', 5, 2)->nullable()->comment('Soil moisture percentage');
            $table->date('test_date')->nullable()->comment('Date when soil test was conducted');
            $table->text('recommendations')->nullable()->comment('Fertilizer and treatment recommendations');
            $table->string('test_method', 50)->default('manual')->comment('Method used for soil testing');
            $table->foreignId('tested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['plot_id', 'test_date']);
            $table->index(['test_method', 'test_date']);
            $table->index(['ph_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soil_health');
    }
};