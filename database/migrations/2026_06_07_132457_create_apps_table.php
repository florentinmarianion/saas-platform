<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apps', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Identity ─────────────────────────────────────────────
            $table->string('slug', 100);           // 'currency-exchange'
            $table->string('module', 100);         // 'CurrencyExchange' (PHP class name)
            $table->string('name', 255);           // 'Currency Exchange'
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();

            // ── Versioning semantică a modulului ─────────────────────
            $table->string('semver', 20)->default('1.0.0');  // versiunea modulului PHP

            // ── Permisiuni declarate de modul ────────────────────────
            // Snapshot al permisiunilor pe care modulul le declară.
            // Ex: ["view","convert","export","reports.view","admin.settings"]
            // Folosit pentru validare la acordarea permisiunilor
            $table->json('declared_permissions')->nullable();

            // ── Configurație ─────────────────────────────────────────
            // Ex: {"min_php":"8.5","requires":["accounting"],"billing_model":"per_user"}
            $table->json('metadata')->nullable();

            // ── Status ───────────────────────────────────────────────
            $table->enum('status', ['active', 'beta', 'deprecated', 'disabled'])
                  ->default('active')
                  ->index();

            // ── Two-Man Rule — Approval pentru înregistrarea unui app nou ─
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                  ->default('approved');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // ── Versioning (Linked List) ─────────────────────────────
            // Util pentru a tracka modificările aduse configurației unui app
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ──────────────────────────────────────────────
            $table->unique(['slug', 'next_id'], 'uq_apps_slug_current');
            $table->unique(['module', 'next_id'], 'uq_apps_module_current');
            $table->index(['status', 'approval_status'], 'idx_apps_status_approval');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apps');
    }
};
