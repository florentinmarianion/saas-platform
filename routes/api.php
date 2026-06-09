<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppController;
use App\Http\Controllers\Api\V1\ApprovalController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api/v1 automatically via bootstrap/app.php
| Auth: Laravel Sanctum (auth:sanctum)
| Tenant: ResolveTenantContext runs on all API routes
|
| Route groups:
|   public    → no auth required (login, accept invitation)
|   platform  → auth required, no company context needed
|   company   → auth + active company context required
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // ── Public routes (no auth) ──────────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');

    // ── Authenticated routes (platform level — no company context needed) ──
    Route::middleware('auth:sanctum')->group(function (): void {

        // Auth
        Route::prefix('auth')->name('auth.')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('switch-company', [AuthController::class, 'switchCompany'])->name('switch-company');
        });

        // Platform — Companies (super-admin)
        Route::apiResource('companies', CompanyController::class);

        // Platform — Users (super-admin)
        Route::apiResource('users', UserController::class);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');

        // Platform — Apps registry (super-admin)
        Route::apiResource('apps', AppController::class);

        // Platform — Roles
        Route::apiResource('roles', RoleController::class);

        // Platform — Approvals
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals/{approval}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approval}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');

        // Platform — Audit (super-admin)
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

        // ── Company-scoped routes (require X-Company-ID header) ──────
        Route::middleware('company')->prefix('company')->name('company.')->group(function (): void {

            // Company users
            Route::get('users', [UserController::class, 'companyIndex'])->name('users.index');
            Route::delete('users/{user}', [UserController::class, 'removeFromCompany'])->name('users.remove');
            Route::patch('users/{user}/status', [UserController::class, 'updateCompanyStatus'])->name('users.status');

            // Company apps
            Route::get('apps', [AppController::class, 'companyIndex'])->name('apps.index');
            Route::post('apps/{app}/activate', [AppController::class, 'activate'])->name('apps.activate');
            Route::post('apps/{app}/deactivate', [AppController::class, 'deactivate'])->name('apps.deactivate');

            // Permissions — per user within company
            Route::prefix('users/{user}')->name('users.')->group(function (): void {
                // Platform permissions
                Route::get('platform-permissions', [PermissionController::class, 'platformIndex'])->name('platform-permissions.index');
                Route::post('platform-permissions', [PermissionController::class, 'grantPlatform'])->name('platform-permissions.grant');
                Route::delete('platform-permissions/{permission}', [PermissionController::class, 'revokePlatform'])->name('platform-permissions.revoke');

                // App permissions
                Route::prefix('apps/{app}')->name('apps.')->group(function (): void {
                    Route::get('permissions', [PermissionController::class, 'appIndex'])->name('permissions.index');
                    Route::get('permissions/history/{permission}', [PermissionController::class, 'history'])->name('permissions.history');
                    Route::post('permissions', [PermissionController::class, 'grant'])->name('permissions.grant');
                    Route::delete('permissions/{permission}', [PermissionController::class, 'revoke'])->name('permissions.revoke');
                    Route::post('permissions/{permissionId}/rollback', [PermissionController::class, 'rollback'])->name('permissions.rollback');
                });
            });

            // Invitations
            Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
            Route::post('invitations', [InvitationController::class, 'store'])->name('invitations.store');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'cancel'])->name('invitations.cancel');

            // Company audit log
            Route::get('audit', [AuditLogController::class, 'companyIndex'])->name('audit.index');
        });
    });
});
