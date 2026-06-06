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
                if (! Schema::hasColumn('disease_reports', 'verified_by')) {
                    $table->foreignId('verified_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('disease_reports', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('verified_by');
                }
                if (! Schema::hasColumn('disease_reports', 'reviewed_by')) {
                    $table->foreignId('reviewed_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('disease_reports', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
                }
                if (! Schema::hasColumn('disease_reports', 'decision_reason_code')) {
                    $table->string('decision_reason_code', 60)->nullable()->after('reviewed_at');
                }
                if (! Schema::hasColumn('disease_reports', 'decision_comment')) {
                    $table->text('decision_comment')->nullable()->after('decision_reason_code');
                }
                if (! Schema::hasColumn('disease_reports', 'escalated_to_user_id')) {
                    $table->foreignId('escalated_to_user_id')->nullable()->after('decision_comment')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('disease_reports', 'escalated_at')) {
                    $table->timestamp('escalated_at')->nullable()->after('escalated_to_user_id');
                }
            });
        }

        if (Schema::hasTable('alerts')) {
            Schema::table('alerts', function (Blueprint $table): void {
                if (! Schema::hasColumn('alerts', 'owner_user_id')) {
                    $table->foreignId('owner_user_id')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('alerts', 'last_action_by')) {
                    $table->foreignId('last_action_by')->nullable()->after('owner_user_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('alerts', 'last_action_at')) {
                    $table->timestamp('last_action_at')->nullable()->after('last_action_by');
                }
                if (! Schema::hasColumn('alerts', 'resolution_reason_code')) {
                    $table->string('resolution_reason_code', 60)->nullable()->after('last_action_at');
                }
                if (! Schema::hasColumn('alerts', 'resolution_comment')) {
                    $table->text('resolution_comment')->nullable()->after('resolution_reason_code');
                }
            });
        }

        if (! Schema::hasTable('case_assignments')) {
            Schema::create('case_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('disease_report_id')->constrained('disease_reports')->cascadeOnDelete();
                $table->foreignId('assigned_to_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('assigned_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('priority', 20)->default('normal');
                $table->timestamp('due_at')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
                $table->index(['assigned_to_user_id', 'status']);
                $table->index(['disease_report_id', 'status']);
            });
        }

        if (! Schema::hasTable('case_audit_logs')) {
            Schema::create('case_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('entity_type', 60);
                $table->unsignedBigInteger('entity_id');
                $table->string('action', 60);
                $table->string('from_status', 40)->nullable();
                $table->string('to_status', 40)->nullable();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('actor_role', 40)->nullable();
                $table->unsignedBigInteger('actor_region_id')->nullable();
                $table->text('note')->nullable();
                $table->json('meta_json')->nullable();
                $table->timestamps();
                $table->index(['entity_type', 'entity_id']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('case_audit_logs')) {
            Schema::drop('case_audit_logs');
        }

        if (Schema::hasTable('case_assignments')) {
            Schema::drop('case_assignments');
        }

        if (Schema::hasTable('alerts')) {
            Schema::table('alerts', function (Blueprint $table): void {
                foreach (['resolution_comment', 'resolution_reason_code', 'last_action_at', 'last_action_by', 'owner_user_id'] as $column) {
                    if (Schema::hasColumn('alerts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('disease_reports')) {
            Schema::table('disease_reports', function (Blueprint $table): void {
                foreach ([
                    'escalated_at',
                    'escalated_to_user_id',
                    'decision_comment',
                    'decision_reason_code',
                    'reviewed_at',
                    'reviewed_by',
                    'verified_at',
                    'verified_by',
                ] as $column) {
                    if (Schema::hasColumn('disease_reports', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

