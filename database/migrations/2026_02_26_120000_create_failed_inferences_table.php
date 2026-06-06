<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('failed_inferences')) {
            return;
        }

        Schema::create('failed_inferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('disease_report_id')->nullable()->constrained('disease_reports')->nullOnDelete();
            $table->foreignId('crop_id')->nullable()->constrained('crops')->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->string('gate_code', 60);
            $table->unsignedTinyInteger('gate_stage')->nullable();
            $table->string('selected_crop', 100)->nullable();
            $table->string('detected_crop', 100)->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->text('message')->nullable();
            $table->string('model_version', 120)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['gate_code', 'occurred_at']);
            $table->index(['disease_report_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('failed_inferences')) {
            Schema::drop('failed_inferences');
        }
    }
};

