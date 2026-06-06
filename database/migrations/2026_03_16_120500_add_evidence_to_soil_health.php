<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (! Schema::hasColumn('soil_health', 'evidence_url')) {
                $table->string('evidence_url')->nullable()->after('reviewed_at');
            }
            if (! Schema::hasColumn('soil_health', 'evidence_type')) {
                $table->string('evidence_type', 60)->nullable()->after('evidence_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (Schema::hasColumn('soil_health', 'evidence_type')) {
                $table->dropColumn('evidence_type');
            }
            if (Schema::hasColumn('soil_health', 'evidence_url')) {
                $table->dropColumn('evidence_url');
            }
        });
    }
};
