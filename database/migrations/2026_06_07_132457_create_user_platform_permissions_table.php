<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permisiuni globale la nivel de platformă.
     * Înlocuiesc complet Spatie Laravel Permission.
     *
     * Pattern: "platform.{resource}.{action}"
     * Exemple:
     *   - platform.companies.create
     *   - platform.companies.manage_all
     *   - platform.users.manage
     *   - platform.apps.register
     *   - platform.audit.read
     *   - platform.billing.manage
     *
     * Un user fără NICIO permisiune platform.* este "member" simplu.
     * Un user cu "platform.*" wildcard este super-admin.
     */
    public function up(): void
    {
        Schema::create('user_platform_permissions', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Relații ──────────────────────────────────────────────
            $table->uuid('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            // ── Permisiunea ──────────────────────────────────────────
            // Format strict: lowercase, doar litere/cifre/punct/underscore
            // Validat în FormRequest cu regex: /^[a-z][a-z0-9_.]+[a-z0-9]$/
            $table->string('permission', 100);

            // granted=TRUE → permisiune acordată
            // granted=FALSE → permisiune explicit NEGATĂ (override)
            // Negarea explicită are prioritate față de orice rol sau grant
            $table->boolean('granted')->default(true);

            // ── Two-Man Rule ─────────────────────────────────────────
            $table->enum('approval_status', ['pending', 'approved', 'auto_approved'])
                  ->default('auto_approved')
                  ->index();
            $table->uuid('granted_by');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreign('granted_by')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Validitate temporală ─────────────────────────────────
            // valid_until NULL = permanent
            // Cron job zilnic invalideaza/notifica permisiunile expirate
            $table->timestamp('valid_until')->nullable()->index();

            // ── Versioning (Linked List) ─────────────────────────────
            // next_id NULL = versiunea curentă activă
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->timestamp('created_at')->useCurrent();
            // Nu avem updated_at — rândurile sunt imutabile (versioning)

            // ── Indexes ──────────────────────────────────────────────
            // O permisiune activă per user (next_id NULL = curentă)
            $table->unique(
                ['user_id', 'permission', 'next_id'],
                'uq_upp_current'
            );
            $table->index(['user_id', 'granted', 'approval_status'], 'idx_upp_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_platform_permissions');
    }
};
