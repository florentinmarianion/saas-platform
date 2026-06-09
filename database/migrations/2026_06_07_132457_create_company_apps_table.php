<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_apps', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Relații Core ─────────────────────────────────────────
            $table->uuid('company_id');
            $table->uuid('app_id');

            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->restrictOnDelete();

            $table->foreign('app_id')
                  ->references('id')
                  ->on('apps')
                  ->restrictOnDelete();

            // ── Subscription / Trial ──────────────────────────────────
            $table->enum('status', ['active', 'suspended', 'trial', 'expired'])
                  ->default('active')
                  ->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            // ── Config per companie (override față de app defaults) ───
            // Ex: {"max_api_calls":5000,"custom_currency_list":["EUR","USD"]}
            $table->json('config')->nullable();

            // ── Two-Man Rule ─────────────────────────────────────────
            // Activarea unui app scump/critic poate necesita dublu-aprobare
            $table->enum('approval_status', ['pending', 'approved', 'auto_approved'])
                  ->default('auto_approved');
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->foreign('approved_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            // ── Audit ────────────────────────────────────────────────
            $table->uuid('activated_by');
            $table->timestamps();

            $table->foreign('activated_by')
                  ->references('id')
                  ->on('users')
                  ->restrictOnDelete();

            // ── Indexes ──────────────────────────────────────────────
            $table->unique(['company_id', 'app_id'], 'uq_company_app');
            $table->index(['company_id', 'status'], 'idx_ca_company_status');
            $table->index(['status', 'trial_ends_at'], 'idx_ca_trial_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_apps');
    }
};
