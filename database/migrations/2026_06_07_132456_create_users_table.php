<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // ── Primary Key ─────────────────────────────────────────
            $table->uuid('id')->primary();
            // ULID virtual generat în MySQL — util pentru debug/URL-uri
            // BINARY(16) intern, expus ca CHAR(26) în queries

            // ── Identity ─────────────────────────────────────────────
            $table->string('email', 191);
            $table->string('name', 255);
            $table->string('password_hash', 255);
            $table->string('avatar_url', 500)->nullable();
            $table->string('locale', 10)->default('ro');
            $table->string('timezone', 50)->default('Europe/Bucharest');

            // ── Status ───────────────────────────────────────────────
            $table->enum('status', ['active', 'suspended', 'deleted'])
                  ->default('active')
                  ->index();

            // ── Versioning (Linked List) ─────────────────────────────
            // Fiecare modificare creează un rând nou.
            // next_id NULL = versiunea curentă/activă.
            $table->unsignedSmallInteger('version')->default(1);
            $table->uuid('prev_id')->nullable()->index();
            $table->uuid('next_id')->nullable()->index();

            // ── Audit ────────────────────────────────────────────────
            $table->uuid('created_by')->nullable(); // NULL = self-registered / seeder
            $table->timestamps();                   // created_at, updated_at
            $table->softDeletes();                  // deleted_at

            // ── Indexes ──────────────────────────────────────────────
            // Email unic DOAR pe versiunea curentă (next_id IS NULL)
            // MySQL nu suportă partial indexes nativ — folosim unique compus
            // și filtrăm în query prin scope Current
            $table->unique(['email', 'next_id'], 'uq_users_email_current');
            $table->index(['status', 'deleted_at'], 'idx_users_status_deleted');

            // FK self-referențiale — adăugate după crearea tabelului (vezi mai jos)
            // prev_id → users(id) — adăugat în migration separată (circular ref)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
