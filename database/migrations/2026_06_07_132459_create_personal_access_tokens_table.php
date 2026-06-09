<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();

            // Standard Sanctum morphs — tokenable_type + tokenable_id
            // Using uuidMorphs because our User model uses UUID primary keys
            $table->uuidMorphs('tokenable');

            $table->string('name', 191);
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();

            // Company context — our extension
            $table->uuid('company_id')->nullable()->index();
            $table->foreign('company_id')
                  ->references('id')
                  ->on('companies')
                  ->nullOnDelete();

            // Security tracking
            $table->string('device_name', 191)->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->string('created_ip', 45)->nullable();

            // Lifecycle
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable();
            $table->uuid('revoked_by')->nullable();

            $table->foreign('revoked_by')
                  ->references('id')
                  ->on('users')
                  ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
