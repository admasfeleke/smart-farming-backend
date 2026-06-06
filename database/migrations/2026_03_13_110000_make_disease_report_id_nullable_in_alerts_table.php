<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropForeign(['disease_report_id']);
            $table->unsignedBigInteger('disease_report_id')->nullable()->change();
            $table->foreign('disease_report_id')->references('id')->on('disease_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table): void {
            $table->dropForeign(['disease_report_id']);
            $table->unsignedBigInteger('disease_report_id')->nullable(false)->change();
            $table->foreign('disease_report_id')->references('id')->on('disease_reports')->cascadeOnDelete();
        });
    }
};