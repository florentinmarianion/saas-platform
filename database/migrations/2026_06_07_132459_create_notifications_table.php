<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notificări in-app (Laravel Notifications standard + extensii).
     *
     * Tipuri de notificări (type):
     *   - App\Notifications\Permission\PermissionPendingApproval
     *   - App\Notifications\Permission\PermissionApproved
     *   - App\Notifications\Permission\PermissionRejected
     *   - App\Notifications\Invitation\InvitationReceived
     *   - App\Notifications\Company\CompanyUserAdded
     *   - App\Notifications\Approval\ApprovalRequired
     *   - App\Notifications\Security\SuspiciousLoginDetected
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // ── Destinatar (morphable) ───────────────────────────────
            $table->string('notifiable_type');
            $table->uuid('notifiable_id');
            $table->index(['notifiable_type', 'notifiable_id'], 'idx_notif_notifiable');

            // ── Conținut ─────────────────────────────────────────────
            $table->string('type');   // FQCN al clasei Notification
            $table->json('data');     // payload complet al notificării

            // ── Context (opțional, pentru filtrare rapidă) ───────────
            $table->uuid('company_id')->nullable()->index();
            $table->string('priority', 20)->default('normal'); // low, normal, high, urgent

            // ── Lifecycle ────────────────────────────────────────────
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
