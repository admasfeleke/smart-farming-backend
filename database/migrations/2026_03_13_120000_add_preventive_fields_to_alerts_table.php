<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('alerts')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table): void {
            if (! Schema::hasColumn('alerts', 'plot_id')) {
                $table->foreignId('plot_id')->nullable()->constrained('plots')->nullOnDelete();
                $table->index(['plot_id', 'status']);
            }

            if (! Schema::hasColumn('alerts', 'planting_id')) {
                $table->foreignId('planting_id')->nullable()->constrained('plantings')->nullOnDelete();
                $table->index(['planting_id', 'status']);
            }

            if (! Schema::hasColumn('alerts', 'is_preventive')) {
                $table->boolean('is_preventive')->default(false);
                $table->index(['farm_id', 'is_preventive', 'status']);
            }

            if (! Schema::hasColumn('alerts', 'risk_level')) {
                // 0.0000 - 1.0000 range, nullable for non-preventive alerts.
                $table->decimal('risk_level', 5, 4)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('alerts')) {
            return;
        }

        Schema::table('alerts', function (Blueprint $table): void {
            if (Schema::hasColumn('alerts', 'plot_id')) {
                $table->dropConstrainedForeignId('plot_id');
            }

            if (Schema::hasColumn('alerts', 'planting_id')) {
                $table->dropConstrainedForeignId('planting_id');
            }

            if (Schema::hasColumn('alerts', 'is_preventive')) {
                $table->dropColumn('is_preventive');
            }

            if (Schema::hasColumn('alerts', 'risk_level')) {
                $table->dropColumn('risk_level');
            }
        });
    }
};

