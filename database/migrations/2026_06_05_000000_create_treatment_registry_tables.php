<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pesticide_products')) {
            Schema::create('pesticide_products', function (Blueprint $table): void {
                $table->id();
                $table->string('product_name');
                $table->string('active_ingredient');
                $table->string('formulation')->nullable();
                $table->string('product_type')->default('fungicide');
                $table->string('registration_status')->default('locally_verified_required');
                $table->text('label_warning')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['product_type', 'is_active']);
                $table->unique(['product_name', 'active_ingredient']);
            });
        }

        if (! Schema::hasTable('treatment_recommendations')) {
            Schema::create('treatment_recommendations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('crop_id')->nullable()->constrained('crops')->nullOnDelete();
                $table->foreignId('pesticide_product_id')->nullable()->constrained('pesticide_products')->nullOnDelete();
                $table->string('disease_key')->nullable();
                $table->string('disease_keyword')->nullable();
                $table->string('recommendation_type')->default('chemical');
                $table->string('title');
                $table->text('summary')->nullable();
                $table->text('natural_treatment')->nullable();
                $table->text('modern_treatment')->nullable();
                $table->text('dosage_text')->nullable();
                $table->text('application_timing')->nullable();
                $table->unsignedSmallInteger('pre_harvest_interval_days')->nullable();
                $table->unsignedSmallInteger('re_entry_interval_hours')->nullable();
                $table->unsignedTinyInteger('max_applications')->nullable();
                $table->text('ppe')->nullable();
                $table->text('restrictions')->nullable();
                $table->json('monitoring_steps')->nullable();
                $table->json('prevention_steps')->nullable();
                $table->string('approval_status')->default('approved');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['crop_id', 'disease_key', 'is_active', 'approval_status'], 'treatment_crop_disease_status_idx');
                $table->index(['crop_id', 'disease_keyword', 'is_active', 'approval_status'], 'treatment_crop_keyword_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_recommendations');
        Schema::dropIfExists('pesticide_products');
    }
};
