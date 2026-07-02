<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 50)->unique();
                $table->string('description', 255)->nullable();
            });
        }

        if (! Schema::hasTable('regions')) {
            Schema::create('regions', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->foreignId('parent_id')->nullable()->constrained('regions')->nullOnDelete();
                $table->enum('level', ['region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc']);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('crops')) {
            Schema::create('crops', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100)->unique();
                $table->string('scientific_name', 150)->nullable();
                $table->enum('crop_type', ['cereal', 'legume', 'vegetable', 'fruit', 'cash_crop', 'other'])
                    ->default('other');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('farms')) {
            Schema::create('farms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('farmer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('region_id')->constrained('regions')->restrictOnDelete();
                $table->string('farm_name', 100);
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->decimal('area_hectares', 8, 2)->nullable();
                $table->enum('farm_type', ['crop', 'mixed', 'livestock'])->default('crop');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['farmer_id', 'is_active']);
                $table->index(['region_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('plots')) {
            Schema::create('plots', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('farm_id')->constrained('farms')->cascadeOnDelete();
                $table->string('plot_name', 100);
                $table->decimal('area_hectares', 8, 2)->nullable();
                $table->enum('soil_type', ['clay', 'sandy', 'loam', 'silty', 'peaty', 'chalky', 'unknown'])
                    ->default('unknown');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['farm_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('plantings')) {
            Schema::create('plantings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plot_id')->constrained('plots')->cascadeOnDelete();
                $table->foreignId('crop_id')->constrained('crops')->restrictOnDelete();
                $table->date('planting_date');
                $table->date('expected_harvest_date')->nullable();
                $table->enum('status', ['planned', 'active', 'harvested', 'failed'])->default('active');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['plot_id', 'is_active']);
                $table->index(['crop_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: no table drops on rollback.
    }
};
