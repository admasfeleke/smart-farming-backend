<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table): void {
                if (! Schema::hasColumn('users', 'admin_level')) {
                    $table->string('admin_level', 30)->nullable()->after('region_id');
                }
            });
        }

        if (! Schema::hasTable('user_region_scopes')) {
            Schema::create('user_region_scopes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'region_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_region_scopes')) {
            Schema::drop('user_region_scopes');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'admin_level')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('admin_level');
            });
        }
    }
};

