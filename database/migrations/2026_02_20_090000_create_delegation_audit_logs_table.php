<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delegation_audit_logs')) {
            Schema::create('delegation_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 40);
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['actor_user_id', 'created_at']);
                $table->index(['target_user_id', 'created_at']);
                $table->index(['action', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delegation_audit_logs')) {
            Schema::drop('delegation_audit_logs');
        }
    }
};
