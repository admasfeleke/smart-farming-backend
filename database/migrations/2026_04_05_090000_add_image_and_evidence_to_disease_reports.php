<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('disease_reports')) {
            Schema::table('disease_reports', function (Blueprint $table): void {
                if (! Schema::hasColumn('disease_reports', 'image_path')) {
                    $table->string('image_path')->nullable()->after('client_submission_id');
                }
                if (! Schema::hasColumn('disease_reports', 'image_disk')) {
                    $table->string('image_disk', 40)->nullable()->after('image_path');
                }
                if (! Schema::hasColumn('disease_reports', 'image_mime')) {
                    $table->string('image_mime', 120)->nullable()->after('image_disk');
                }
                if (! Schema::hasColumn('disease_reports', 'image_size_bytes')) {
                    $table->unsignedBigInteger('image_size_bytes')->nullable()->after('image_mime');
                }
            });
        }

        if (! Schema::hasTable('disease_report_evidence')) {
            Schema::create('disease_report_evidence', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('disease_report_id')->constrained('disease_reports')->cascadeOnDelete();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('kind', 40);
                $table->string('file_path');
                $table->string('file_disk', 40)->default('public');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->nullable();
                $table->text('caption')->nullable();
                $table->timestamps();

                $table->index(['disease_report_id', 'kind']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('disease_report_evidence')) {
            Schema::drop('disease_report_evidence');
        }

        if (Schema::hasTable('disease_reports')) {
            Schema::table('disease_reports', function (Blueprint $table): void {
                foreach (['image_size_bytes', 'image_mime', 'image_disk', 'image_path'] as $column) {
                    if (Schema::hasColumn('disease_reports', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
