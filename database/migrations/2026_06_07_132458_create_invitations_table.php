<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invitații pentru onboarding utilizatori în companii.
     *
     * Flow:
     *   1. Admin invită email → se creează invitation cu token_hash + permissions_snapshot
     *   2. Email trimis cu link: /invitation/accept?token={raw_token}
     *   3. Utilizatorul accesează link-ul:
     *      a. Dacă are cont → se autentifică, acceptă, devine company member
     *      b. Dacă nu are cont → completează înregistrarea, apoi acceptă
     *   4. La acceptare: se creează company_user + user_app_permissions din snapshot
     *
     * Securitate:
     *   - token_hash = SHA-256 al tokenului raw (tokenul raw nu se stochează NICIODATĂ)
     *   - tokenul raw are 64 bytes random → 512 bits entropie
     *   - expires_at = 72h implicit
     *   - un token acceptat nu mai poate fi refolosit (status='accepted')
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Context ──────────────────────────────────────────────
            $table->uuid('company_id');
            $table->uuid('invited_by');

            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->restrictOnDelete();

            $table->foreign('invited_by')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            // ── Destinatar ───────────────────────────────────────────
            $table->string('email', 191)->index();
            $table->string('name', 255)->nullable(); // pre-fill la înregistrare

            // ── Token (securizat) ────────────────────────────────────
            // NICIODATĂ nu stoca tokenul raw.
            // hash_hmac('sha256', $rawToken, config('app.key'))
            $table->char('token_hash', 64);

            // ── Snapshot permisiuni la momentul invitației ───────────
            // Dacă permisiunile se schimbă după trimitere, invitația
            // păstrează ce i s-a promis utilizatorului invitat.
            // Format: [{"app_slug":"currency-exchange","permissions":["view","convert"]}]
            $table->json('permissions_snapshot')->nullable();

            // ── Metadata ─────────────────────────────────────────────
            $table->string('role_label', 100)->nullable();

            // ── Status ───────────────────────────────────────────────
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])
                  ->default('pending')
                  ->index();

            // ── Timestamps cheie ─────────────────────────────────────
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();

            // ── Acceptat de (poate fi alt user dacă email-ul era deja înregistrat) ─
            $table->uuid('accepted_by')->nullable();
            $table->foreign('accepted_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Audit ────────────────────────────────────────────────
            $table->timestamp('created_at')->useCurrent();

            // ── Indexes ──────────────────────────────────────────────
            $table->unique('token_hash', 'uq_inv_token');
            $table->index(['company_id', 'status'], 'idx_inv_company_status');
            $table->index(['email', 'status'], 'idx_inv_email_status');
            $table->index(['status', 'expires_at'], 'idx_inv_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
