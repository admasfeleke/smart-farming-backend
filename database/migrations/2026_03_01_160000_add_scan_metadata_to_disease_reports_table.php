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
            if (! Schema::hasColumn('disease_reports', 'scan_metadata')) {
                $table->json('scan_metadata')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: no column drops on rollback.
    }
};

