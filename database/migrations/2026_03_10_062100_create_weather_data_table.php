<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_data', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
            $table->decimal('temperature', 5, 2)->nullable()->comment('Temperature in Celsius');
            $table->decimal('humidity', 5, 2)->nullable()->comment('Relative humidity percentage');
            $table->decimal('precipitation', 6, 2)->nullable()->comment('Precipitation in mm');
            $table->decimal('wind_speed', 5, 2)->nullable()->comment('Wind speed in km/h');
            $table->decimal('soil_moisture', 5, 2)->nullable()->comment('Soil moisture percentage');
            $table->string('data_source', 50)->default('manual')->comment('Source of weather data');
            $table->timestamp('recorded_at')->nullable()->comment('When the data was recorded');
            $table->timestamps();

            $table->index(['region_id', 'recorded_at']);
            $table->index(['farm_id', 'recorded_at']);
            $table->index(['plot_id', 'recorded_at']);
            $table->index(['data_source', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_data');
    }
};