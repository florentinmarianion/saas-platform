<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug', 100);
            $table->string('name', 255);
            $table->string('legal_name', 255)->nullable();
            $table->string('vat_number', 50)->nullable();
            $table->string('country', 2)->default('RO');
            $table->string('logo_url', 500)->nullable();
            $table->json('settings')->nullable();
            $table->enum('status', ['active', 'suspended', 'archived', 'deleted'])
                ->default('active')
                ->index();
            $table->enum('approval_status', ['draft', 'pending', 'approved', 'rejected'])
                ->default('approved')
                ->index();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'next_id'], 'uq_companies_slug_current');
            $table->index(['status', 'approval_status'], 'idx_companies_status_approval');
            $table->index(['country', 'status'], 'idx_companies_country_status');

            // NOTE: JSON functional index on settings->plan is intentionally omitted.
            // Add manually on MySQL 8.0.13+ in production if needed:
            // ALTER TABLE companies ADD INDEX idx_settings_plan ((CAST(settings->>'$.plan' AS CHAR(50))));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
