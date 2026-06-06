<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (! Schema::hasColumn('users', 'phone') || ! Schema::hasColumn('users', 'is_active')) {
            return;
        }

        if (! Schema::hasIndex('users', 'users_phone_is_active_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index(['phone', 'is_active'], 'users_phone_is_active_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (Schema::hasIndex('users', 'users_phone_is_active_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_phone_is_active_index');
            });
        }
    }
};
