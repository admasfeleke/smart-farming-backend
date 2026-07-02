<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('regions') || ! Schema::hasColumn('regions', 'level')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `regions` MODIFY `level` ENUM('region', 'zone', 'special_woreda', 'woreda', 'kebele', 'ftc') NOT NULL");
    }

    public function down(): void
    {
        // Intentionally non-destructive. Existing records may use special_woreda or ftc.
    }
};
