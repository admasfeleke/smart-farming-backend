<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (! Schema::hasColumn('soil_health', 'review_status')) {
                $table->string('review_status', 20)->default('pending')->after('test_method');
            }
            if (! Schema::hasColumn('soil_health', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->after('review_status');
            }
            if (! Schema::hasColumn('soil_health', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
        });

        Schema::table('soil_health', function (Blueprint $table): void {
            $table->index('review_status');
            $table->index('reviewed_at');
        });

        DB::table('soil_health')
            ->whereNull('review_status')
            ->update(['review_status' => 'validated']);
    }

    public function down(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (Schema::hasColumn('soil_health', 'reviewed_at')) {
                $table->dropIndex(['reviewed_at']);
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('soil_health', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('soil_health', 'review_status')) {
                $table->dropIndex(['review_status']);
                $table->dropColumn('review_status');
            }
        });
    }
};
