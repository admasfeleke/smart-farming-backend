<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_assignments', function (Blueprint $table): void {
            // Explicit completion timestamp so resolution time is accurate even if
            // the record is later touched for other reasons (e.g. audit updates).
            // Backfilled from updated_at for existing completed rows.
            $table->timestamp('completed_at')->nullable()->after('due_at');
            $table->index(['assigned_to_user_id', 'status', 'completed_at'], 'ca_assignee_status_completed_idx');
        });

        // Backfill: for already-completed assignments, use updated_at as the
        // best available approximation of when they were completed.
        DB::statement("
            UPDATE case_assignments
            SET completed_at = updated_at
            WHERE status = 'completed' AND completed_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('case_assignments', function (Blueprint $table): void {
            $table->dropIndex('ca_assignee_status_completed_idx');
            $table->dropColumn('completed_at');
        });
    }
};
