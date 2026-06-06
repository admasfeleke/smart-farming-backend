<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('disease_reports')) {
            return;
        }

        Schema::table('disease_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('disease_reports', 'client_submission_id')) {
                $table->string('client_submission_id', 100)->nullable()->after('reported_by');
                $table->unique(
                    ['reported_by', 'client_submission_id'],
                    'dr_reported_by_client_submission_unique'
                );
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: no column or index drops on rollback.
    }
};

