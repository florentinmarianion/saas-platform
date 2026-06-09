<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles are permission templates applied at invitation/creation time.
     * After that, permissions are managed independently per user.
     *
     * A role defines:
     *   - A label shown in the UI (e.g. "Accountant", "HR Specialist")
     *   - A set of default platform permissions
     *   - A set of default app permissions per app slug
     *
     * At runtime, ONLY user_app_permissions and user_platform_permissions
     * are checked. The role is never evaluated directly.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table): void {
            // ── Primary Key ──────────────────────────────────────────
            $table->uuid('id')->primary();

            // ── Identity ─────────────────────────────────────────────
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->text('description')->nullable();

            // ── Scope ────────────────────────────────────────────────
            // platform roles: visible to all companies (e.g. Admin, Member)
            // company roles:  visible only to a specific company (custom roles)
            $table->enum('scope', ['platform', 'company'])->default('platform');
            $table->uuid('company_id')->nullable()->index();

            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->cascadeOnDelete();

            // ── Default permissions snapshot ─────────────────────────
            // Applied when a user is invited or created with this role.
            // Format:
            // {
            //   "platform": ["platform.companies.create"],
            //   "apps": {
            //     "currency-exchange": ["view", "convert"],
            //     "accounting": ["view", "reports.view"]
            //   }
            // }
            $table->json('default_permissions')->nullable();

            // ── Versioning (Linked List) ─────────────────────────────
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ──────────────────────────────────────────────
            $table->unique(['slug', 'company_id', 'next_id'], 'uq_roles_slug_current');
            $table->index(['scope', 'deleted_at'], 'idx_roles_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
