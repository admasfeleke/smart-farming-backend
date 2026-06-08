<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('case_assignments')) {
            return;
        }

        if (Schema::hasColumn('case_assignments', 'disease_report_id')) {
            DB::statement('ALTER TABLE case_assignments MODIFY disease_report_id BIGINT UNSIGNED NULL');
        }

        Schema::table('case_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('case_assignments', 'soil_health_id')) {
                $table->foreignId('soil_health_id')
                    ->nullable()
                    ->after('disease_report_id')
                    ->constrained('soil_health')
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('case_assignments', 'case_type')) {
                $table->string('case_type', 40)
                    ->default('disease_report')
                    ->after('soil_health_id');
            }
        });

        Schema::table('case_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('case_assignments', 'soil_health_id')) {
                $table->index(['soil_health_id', 'status'], 'case_assignments_soil_status_idx');
            }
            if (Schema::hasColumn('case_assignments', 'case_type')) {
                $table->index(['case_type', 'assigned_to_user_id', 'status'], 'case_assignments_type_assignee_status_idx');
            }
        });

        if (Schema::hasColumn('case_assignments', 'disease_report_id')) {
            DB::statement("UPDATE case_assignments SET case_type = 'disease_report' WHERE case_type IS NULL OR case_type = ''");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('case_assignments')) {
            return;
        }

        Schema::table('case_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('case_assignments', 'soil_health_id')) {
                $table->dropIndex('case_assignments_soil_status_idx');
            }
            if (Schema::hasColumn('case_assignments', 'case_type')) {
                $table->dropIndex('case_assignments_type_assignee_status_idx');
            }
        });

        Schema::table('case_assignments', function (Blueprint $table): void {
            if (Schema::hasColumn('case_assignments', 'case_type')) {
                $table->dropColumn('case_type');
            }
            if (Schema::hasColumn('case_assignments', 'soil_health_id')) {
                $table->dropConstrainedForeignId('soil_health_id');
            }
        });

        if (Schema::hasColumn('case_assignments', 'disease_report_id')) {
            DB::statement("DELETE FROM case_assignments WHERE disease_report_id IS NULL");
            DB::statement('ALTER TABLE case_assignments MODIFY disease_report_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
