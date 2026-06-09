<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Coada centralizată pentru Two-Man Rule (4-eyes principle).
     *
     * Orice entitate care necesită dublu-aprobare creează un ApprovalRequest.
     * Entitățile suportate (entity_type):
     *   - UserAppPermission    → permisiuni sensibile (salary.view, trade.execute)
     *   - UserPlatformPermission → permisiuni platform critice
     *   - CompanyUser          → adăugarea unui user în companie
     *   - CompanyApp           → activarea unui app critic/scump
     *   - Company              → crearea unei companii noi (dacă platforma e closed)
     *
     * Flow:
     *   1. User A propune acțiune → entitate creată cu approval_status='pending'
     *      + ApprovalRequest creat cu status='pending'
     *   2. Notificare trimisă tuturor Approver-ilor eligibili
     *   3. User B (≠ User A) aprobă/respinge
     *   4. La aprobare: entitatea trece în approval_status='approved' și devine activă
     *   5. La respingere: entitatea rămâne inactivă, User A primește notificare
     *
     * Reguli:
     *   - Approver-ul NU poate fi același cu requestor-ul
     *   - Un request expirat automat după expires_at → status='expired'
     *   - Idempotent: un singur ApprovalRequest activ per entitate
     */
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Entitatea care necesită aprobare (polymorphic manual) ─
            // Nu folosim morphs() Laravel — tipul e strict enum pentru securitate
            $table->enum('entity_type', [
                'UserAppPermission',
                'UserPlatformPermission',
                'CompanyUser',
                'CompanyApp',
                'Company',
            ]);
            $table->uuid('entity_id');

            // ── Acțiunea cerută ──────────────────────────────────────
            // Ex: 'grant_permission', 'revoke_permission', 'add_member',
            //     'activate_app', 'suspend_user'
            $table->string('action', 100);

            // ── Participanți ─────────────────────────────────────────
            $table->uuid('requested_by');
            $table->uuid('reviewed_by')->nullable();  // cel care a aprobat/respins

            $table->foreign('requested_by')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            $table->foreign('reviewed_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Status ───────────────────────────────────────────────
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired', 'cancelled'])
                  ->default('pending')
                  ->index();

            // ── Payload complet al acțiunii ──────────────────────────
            // Stochează toate datele necesare pentru a executa acțiunea la aprobare
            // sau pentru audit complet al ce s-a cerut.
            // Ex: {"permission":"salary.view","user_id":"...","reason":"Audit extern Q4"}
            $table->json('payload')->nullable();

            // ── Motivul (opțional, dar recomandat pentru audit) ──────
            $table->text('rejection_reason')->nullable();
            $table->text('requester_note')->nullable();

            // ── Timing ───────────────────────────────────────────────
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('expires_at')->index();  // auto-expire dacă nu e revizuit

            // ── Audit ────────────────────────────────────────────────
            $table->timestamps();

            // ── Indexes ──────────────────────────────────────────────
            $table->index(['entity_type', 'entity_id'], 'idx_ar_entity');
            $table->index(['requested_by', 'status'], 'idx_ar_requestor_status');
            $table->index(['reviewed_by', 'status'], 'idx_ar_reviewer_status');
            $table->index(['status', 'expires_at'], 'idx_ar_pending_expiry');

            // Garantează un singur request activ per entitate
            $table->unique(
                ['entity_type', 'entity_id', 'action', 'status'],
                'uq_ar_active_per_entity'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
