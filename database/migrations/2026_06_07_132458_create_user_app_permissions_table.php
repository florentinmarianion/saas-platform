<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permisiuni granulare per User × Company × App.
     * Acesta este centrul întregului sistem de autorizare.
     *
     * Pattern permisiune: "{resource}.{action}" (fără prefixul app-ului)
     * Context-ul app-ului este dat de app_id.
     *
     * Exemple pentru app "accounting":
     *   - view
     *   - invoices.create
     *   - invoices.approve        ← Two-Man Rule tipic
     *   - reports.export
     *   - salary.view             ← date sensibile
     *
     * Exemple pentru app "currency-exchange":
     *   - view
     *   - convert
     *   - export
     *   - rates.manage
     *
     * Logica de verificare:
     *   1. Există o înregistrare curentă (next_id IS NULL)?
     *   2. granted = TRUE?
     *   3. approval_status IN ('approved', 'auto_approved')?
     *   4. valid_until IS NULL OR valid_until > NOW()?
     *   → Dacă DA la toate: permisiunea este activă.
     *
     * Negarea explicită (granted=FALSE) blochează accesul chiar dacă
     * există un grant anterior în istoric.
     */
    public function up(): void
    {
        Schema::create('user_app_permissions', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Context (triplet unic) ───────────────────────────────
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->uuid('app_id');

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->restrictOnDelete();

            $table->foreign('app_id')
                  ->references('id')
                  ->on('apps')
                  ->restrictOnDelete();

            // ── Permisiunea ──────────────────────────────────────────
            $table->string('permission', 100);
            $table->boolean('granted')->default(true);

            // ── Two-Man Rule ─────────────────────────────────────────
            // Permisiunile sensibile (salary.view, invoices.approve)
            // necesită aprobarea unui al doilea utilizator autorizat.
            // 'auto_approved' = permisiunile standard fără risc ridicat
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
            // Exemplu: acces temporar auditor extern (30 de zile)
            $table->timestamp('valid_from')->useCurrent();
            $table->timestamp('valid_until')->nullable()->index();

            // ── Versioning (Linked List) ─────────────────────────────
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->timestamp('created_at')->useCurrent();
            // Fără updated_at — rândurile sunt IMUTABILE

            // ── Indexes ──────────────────────────────────────────────
            // Permisiune activă unică per triplet (user, company, app, permission)
            // next_id NULL = versiunea curentă
            $table->unique(
                ['user_id', 'company_id', 'app_id', 'permission', 'next_id'],
                'uq_uap_current'
            );

            // Index principal pentru verificarea rapidă în runtime
            // Acesta este cel mai folosit index din întreaga platformă
            $table->index(
                ['user_id', 'company_id', 'app_id', 'granted', 'approval_status'],
                'idx_uap_check'
            );

            // Index pentru admin panel: toate permisiunile în așteptare
            $table->index(['approval_status', 'valid_until'], 'idx_uap_pending');

            // Index pentru CTE recursive (parcurgere linked list)
            $table->index(['prev_id'], 'idx_uap_prev');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_app_permissions');
    }
};
