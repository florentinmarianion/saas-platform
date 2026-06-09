<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Permission\GrantPermissionRequest;
use App\Models\App;
use App\Models\User;
use App\Models\UserAppPermission;
use App\Models\UserPlatformPermission;
use App\Services\TenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    // ── App Permissions ──────────────────────────────────────────────

    public function appIndex(User $user, App $app): JsonResponse
    {
        $permissions = UserAppPermission::forContext(
            userId:    $user->id,
            companyId: $this->context->companyId(),
            appId:     $app->id,
        )->active()->get();

        return response()->json($permissions);
    }

    public function grant(GrantPermissionRequest $request, User $user, App $app): JsonResponse
    {
        $this->authorize('permission.grant');
        $companyId  = $this->context->companyId();
        $permission = $request->permission;

        // Validate permission is declared by the app
        if (!$app->declaresPermission($permission)) {
            return response()->json([
                'message' => "Permission [{$permission}] is not declared by app [{$app->slug}].",
                'code'    => 'PERMISSION_NOT_DECLARED',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Determine approval status based on sensitivity
        $approvalStatus = $app->isSensitivePermission($permission)
            ? 'pending'
            : 'auto_approved';

        $newId = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $user, $app, $companyId, $permission,
            $approvalStatus, $newId, $request
        ): void {
            // Archive current version if exists
            $current = UserAppPermission::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->update(['next_id' => $newId]);
            }

            // Create new version
            UserAppPermission::insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'company_id'      => $companyId,
                'app_id'          => $app->id,
                'permission'      => $permission,
                'granted'         => true,
                'granted_by'      => $request->user()->id,
                'approval_status' => $approvalStatus,
                'valid_from'      => now(),
                'valid_until'     => $request->valid_until,
                'version'         => $current ? $current->version + 1 : 1,
                'prev_id'         => $current?->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);
        });

        $created = UserAppPermission::find($newId);

        return response()->json($created, Response::HTTP_CREATED);
    }

    public function revoke(Request $request, User $user, App $app, string $permission): JsonResponse
    {
        $this->authorize('permission.revoke');
        $companyId = $this->context->companyId();
        $newId     = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $user, $app, $companyId, $permission, $newId, $request
        ): void {
            $current = UserAppPermission::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->firstOrFail();

            $current->update(['next_id' => $newId]);

            UserAppPermission::insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'company_id'      => $companyId,
                'app_id'          => $app->id,
                'permission'      => $permission,
                'granted'         => false,  // explicit revoke
                'granted_by'      => $request->user()->id,
                'approval_status' => 'auto_approved',
                'valid_from'      => now(),
                'valid_until'     => null,
                'version'         => $current->version + 1,
                'prev_id'         => $current->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function history(User $user, App $app, string $permission): JsonResponse
    {
        $companyId = $this->context->companyId();

        // Walk the linked list from current version backwards
        $history = UserAppPermission::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('app_id', $app->id)
            ->where('permission', $permission)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json($history);
    }

    public function rollback(Request $request, User $user, App $app, string $permissionId): JsonResponse
    {
        $target    = UserAppPermission::findOrFail($permissionId);
        $companyId = $this->context->companyId();
        $newId     = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $target, $user, $app, $companyId, $newId, $request
        ): void {
            $current = UserAppPermission::where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $target->permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->update(['next_id' => $newId]);
            }

            UserAppPermission::insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'company_id'      => $companyId,
                'app_id'          => $app->id,
                'permission'      => $target->permission,
                'granted'         => $target->granted,
                'granted_by'      => $request->user()->id,
                'approval_status' => 'auto_approved',
                'valid_from'      => now(),
                'valid_until'     => $target->valid_until,
                'version'         => $current ? $current->version + 1 : 1,
                'prev_id'         => $current?->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);
        });

        return response()->json(['message' => 'Permission rolled back successfully.']);
    }

    // ── Platform Permissions ─────────────────────────────────────────

    public function platformIndex(User $user): JsonResponse
    {
        $permissions = UserPlatformPermission::where('user_id', $user->id)
            ->active()
            ->get();

        return response()->json($permissions);
    }

    public function grantPlatform(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permission'  => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]+[a-z0-9]$/'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ]);

        $newId = (string) Str::orderedUuid();

        DB::transaction(function () use ($user, $request, $newId): void {
            $current = UserPlatformPermission::where('user_id', $user->id)
                ->where('permission', $request->permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                $current->update(['next_id' => $newId]);
            }

            UserPlatformPermission::insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'permission'      => $request->permission,
                'granted'         => true,
                'granted_by'      => $request->user()->id,
                'approval_status' => 'auto_approved',
                'valid_until'     => $request->valid_until,
                'version'         => $current ? $current->version + 1 : 1,
                'prev_id'         => $current?->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);
        });

        return response()->json(
            UserPlatformPermission::find($newId),
            Response::HTTP_CREATED
        );
    }

    public function revokePlatform(Request $request, User $user, string $permission): JsonResponse
    {
        $newId = (string) Str::orderedUuid();

        DB::transaction(function () use ($user, $permission, $newId, $request): void {
            $current = UserPlatformPermission::where('user_id', $user->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->firstOrFail();

            $current->update(['next_id' => $newId]);

            UserPlatformPermission::insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'permission'      => $permission,
                'granted'         => false,
                'granted_by'      => $request->user()->id,
                'approval_status' => 'auto_approved',
                'valid_until'     => null,
                'version'         => $current->version + 1,
                'prev_id'         => $current->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
