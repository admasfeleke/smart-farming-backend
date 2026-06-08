<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (! Schema::hasColumn('soil_health', 'data_source')) {
                $table->string('data_source', 50)->nullable()->after('test_method');
            }
            if (! Schema::hasColumn('soil_health', 'sensor_device_id')) {
                $table->string('sensor_device_id', 120)->nullable()->after('data_source');
            }
            if (! Schema::hasColumn('soil_health', 'sensor_reading_id')) {
                $table->string('sensor_reading_id', 160)->nullable()->after('sensor_device_id');
            }
            if (! Schema::hasColumn('soil_health', 'sensor_payload')) {
                $table->json('sensor_payload')->nullable()->after('sensor_reading_id');
            }
            if (! Schema::hasColumn('soil_health', 'field_context')) {
                $table->json('field_context')->nullable()->after('sensor_payload');
            }
            if (! Schema::hasColumn('soil_health', 'confidence_score')) {
                $table->decimal('confidence_score', 5, 2)->nullable()->after('field_context');
            }
        });

        Schema::table('weather_data', function (Blueprint $table): void {
            if (! Schema::hasColumn('weather_data', 'sensor_device_id')) {
                $table->string('sensor_device_id', 120)->nullable()->after('data_source');
            }
            if (! Schema::hasColumn('weather_data', 'sensor_reading_id')) {
                $table->string('sensor_reading_id', 160)->nullable()->after('sensor_device_id');
            }
            if (! Schema::hasColumn('weather_data', 'sensor_payload')) {
                $table->json('sensor_payload')->nullable()->after('sensor_reading_id');
            }
            if (! Schema::hasColumn('weather_data', 'field_context')) {
                $table->json('field_context')->nullable()->after('sensor_payload');
            }
            if (! Schema::hasColumn('weather_data', 'battery_level')) {
                $table->decimal('battery_level', 5, 2)->nullable()->after('field_context');
            }
            if (! Schema::hasColumn('weather_data', 'signal_quality')) {
                $table->decimal('signal_quality', 5, 2)->nullable()->after('battery_level');
            }
        });

        Schema::table('disease_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('disease_reports', 'field_context')) {
                $table->json('field_context')->nullable()->after('scan_metadata');
            }
        });
    }

    public function down(): void
    {
        Schema::table('disease_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('disease_reports', 'field_context')) {
                $table->dropColumn('field_context');
            }
        });

        Schema::table('weather_data', function (Blueprint $table): void {
            foreach ([
                'signal_quality',
                'battery_level',
                'field_context',
                'sensor_payload',
                'sensor_reading_id',
                'sensor_device_id',
            ] as $column) {
                if (Schema::hasColumn('weather_data', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('soil_health', function (Blueprint $table): void {
            foreach ([
                'confidence_score',
                'field_context',
                'sensor_payload',
                'sensor_reading_id',
                'sensor_device_id',
                'data_source',
            ] as $column) {
                if (Schema::hasColumn('soil_health', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
