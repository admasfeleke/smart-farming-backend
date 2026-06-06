<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disease_reports')) {
            Schema::create('disease_reports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('plot_id')->constrained('plots')->cascadeOnDelete();
                $table->foreignId('crop_id')->constrained('crops')->cascadeOnDelete();
                $table->foreignId('planting_id')->nullable()->constrained('plantings')->nullOnDelete();
                $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('disease_name');
                $table->text('description')->nullable();
                $table->string('report_source')->default('manual');
                $table->decimal('confidence_score', 5, 4)->nullable();
                $table->string('severity')->default('low');
                $table->string('status')->default('new');
                $table->timestamp('reported_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('alerts')) {
            Schema::create('alerts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('disease_report_id')->constrained('disease_reports')->cascadeOnDelete();
                $table->string('alert_type');
                $table->string('severity');
                $table->string('title');
                $table->text('message');
                $table->string('status')->default('open');
                $table->timestamp('triggered_at');
                $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acknowledged_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('alerts')) {
            Schema::drop('alerts');
        }

        if (Schema::hasTable('disease_reports')) {
            Schema::drop('disease_reports');
        }
    }
};
