<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereRaw('LOWER(roles.name) = ?', ['farmer'])
            ->whereNull('users.region_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('farms')
                    ->whereColumn('farms.farmer_id', 'users.id')
                    ->whereNotNull('farms.region_id');
            })
            ->update([
                'users.region_id' => DB::raw('(SELECT farms.region_id FROM farms WHERE farms.farmer_id = users.id AND farms.region_id IS NOT NULL ORDER BY farms.id DESC LIMIT 1)'),
                'users.updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data backfill only. Region values are intentionally preserved.
    }
};
