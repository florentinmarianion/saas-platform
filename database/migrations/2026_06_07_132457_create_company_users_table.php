<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_users', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Relații Core ─────────────────────────────────────────
            $table->uuid('company_id');
            $table->uuid('user_id');

            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->restrictOnDelete();

            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            // ── Role Label (DOAR informativ, NU implică permisiuni) ───
            // Permisiunile reale sunt în user_app_permissions și
            // user_platform_permissions. Acesta e doar un label afișat în UI.
            // Ex: "Manager", "Contabil", "HR Specialist", "Receptionist"
            $table->string('role_label', 100)->nullable();

            // ── Flags ────────────────────────────────────────────────
            // is_owner = creatorul companiei; nu poate fi revocat fără transfer
            $table->boolean('is_owner')->default(false);

            // ── Status membership ────────────────────────────────────
            $table->enum('status', ['active', 'suspended', 'invited', 'removed'])
                  ->default('active')
                  ->index();

            // ── Two-Man Rule ─────────────────────────────────────────
            // Adăugarea unui user într-o companie poate necesita
            // aprobarea unui al doilea admin (nu cel care a invitat)
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                  ->default('approved')
                  ->index();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Versioning (Linked List) ─────────────────────────────
            // Orice modificare (suspend, role_label change) creează versiune nouă
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->uuid('invited_by')->nullable();    // cine a trimis invitația
            $table->timestamp('joined_at')->nullable(); // când a acceptat
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('invited_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Indexes ──────────────────────────────────────────────
            // Un user poate fi în aceeași companie o singură dată (versiunea curentă)
            $table->unique(['company_id', 'user_id', 'next_id'], 'uq_cu_active_membership');
            $table->index(['company_id', 'status'], 'idx_cu_company_status');
            $table->index(['user_id', 'status'], 'idx_cu_user_status');
            $table->index(['approval_status', 'status'], 'idx_cu_approval');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_users');
    }
};
