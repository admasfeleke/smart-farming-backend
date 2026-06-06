<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alerts') && ! Schema::hasColumn('alerts', 'farm_id')) {
            Schema::table('alerts', function (Blueprint $table): void {
                $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete()->after('disease_report_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('alerts') && Schema::hasColumn('alerts', 'farm_id')) {
            Schema::table('alerts', function (Blueprint $table): void {
                $table->dropForeign(['farm_id']);
                $table->dropColumn('farm_id');
            });
        }
    }
};
