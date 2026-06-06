<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (! Schema::hasColumn('soil_health', 'review_reason_code')) {
                $table->string('review_reason_code', 80)->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('soil_health', 'review_comment')) {
                $table->text('review_comment')->nullable()->after('review_reason_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('soil_health', function (Blueprint $table): void {
            if (Schema::hasColumn('soil_health', 'review_comment')) {
                $table->dropColumn('review_comment');
            }

            if (Schema::hasColumn('soil_health', 'review_reason_code')) {
                $table->dropColumn('review_reason_code');
            }
        });
    }
};
