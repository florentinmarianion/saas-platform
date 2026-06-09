<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        /**
         * Log de audit imutabil.
         *
         * Principii:
         *   - NICIODATĂ nu faci UPDATE sau DELETE pe acest tabel
         *   - Rândurile sunt append-only (INSERT only)
         *   - ROW_FORMAT=COMPRESSED — cresc rapid, economisim spațiu
         *   - Retenție: după X luni se arhivează în cold storage (S3/MinIO)
         *     și se purjează din DB prin job programat
         *
         * Acțiuni înregistrate (action):
         *   Platform: user.login, user.logout, user.created, user.suspended
         *   Company:  company.created, company.updated, company.suspended
         *   Members:  company_user.added, company_user.removed
         *   Apps:     app.activated, app.suspended
         *   Perms:    permission.granted, permission.revoked, permission.pending
         *   Approval: approval.requested, approval.approved, approval.rejected
         *   Data:     export.csv, export.pdf
         *   Security: auth.failed, auth.mfa_failed, token.revoked
         */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->uuid('company_id')->nullable();
            $table->uuid('app_id')->nullable();
            $table->string('action', 100);
            $table->string('entity_type', 100);
            $table->uuid('entity_id');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('request_id', 36)->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index('user_id', 'idx_al_user');
            $table->index(['entity_type', 'entity_id'], 'idx_al_entity');
            $table->index('company_id', 'idx_al_company');
            $table->index('action', 'idx_al_action');
            $table->index('created_at', 'idx_al_created');

            // NOTE: ROW_FORMAT=COMPRESSED and JSON functional indexes
            // are intentionally omitted for database portability.
            // Apply manually on MySQL 8.0.13+ in production.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
