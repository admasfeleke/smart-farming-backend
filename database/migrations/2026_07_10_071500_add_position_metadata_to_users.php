<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'technical_domain')) {
                $table->string('technical_domain', 60)->nullable()->after('admin_level');
            }

            if (! Schema::hasColumn('users', 'position_title')) {
                $table->string('position_title', 160)->nullable()->after('technical_domain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'position_title')) {
                $table->dropColumn('position_title');
            }

            if (Schema::hasColumn('users', 'technical_domain')) {
                $table->dropColumn('technical_domain');
            }
        });
    }
};
