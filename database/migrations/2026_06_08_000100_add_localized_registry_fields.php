<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesticide_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('pesticide_products', 'localized_names')) {
                $table->json('localized_names')->nullable()->after('product_name');
            }
            if (! Schema::hasColumn('pesticide_products', 'localized_active_ingredients')) {
                $table->json('localized_active_ingredients')->nullable()->after('active_ingredient');
            }
            if (! Schema::hasColumn('pesticide_products', 'localized_label_warnings')) {
                $table->json('localized_label_warnings')->nullable()->after('label_warning');
            }
        });

        Schema::table('treatment_recommendations', function (Blueprint $table): void {
            if (! Schema::hasColumn('treatment_recommendations', 'localized_content')) {
                $table->json('localized_content')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('treatment_recommendations', function (Blueprint $table): void {
            if (Schema::hasColumn('treatment_recommendations', 'localized_content')) {
                $table->dropColumn('localized_content');
            }
        });

        Schema::table('pesticide_products', function (Blueprint $table): void {
            foreach ([
                'localized_label_warnings',
                'localized_active_ingredients',
                'localized_names',
            ] as $column) {
                if (Schema::hasColumn('pesticide_products', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
