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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PermissionController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    // ── App Permissions ──────────────────────────────────────────────

    public function appIndex(User $user, App $app): AnonymousResourceCollection
    {
        $permissions = UserAppPermission::where('user_id', $user->id)
            ->where('company_id', $this->context->companyId())
            ->where('app_id', $app->id)
            ->active()
            ->get();

        return \App\Http\Resources\V1\UserAppPermissionResource::collection($permissions);
    }

    public function grant(GrantPermissionRequest $request, User $user, App $app): JsonResponse
    {
        $this->authorize('permission.grant');

        $companyId  = $this->context->companyId();
        $permission = $request->permission;

        if (!$app->declaresPermission($permission)) {
            return response()->json([
                'message' => "Permission [{$permission}] is not declared by app [{$app->slug}].",
                'code'    => 'PERMISSION_NOT_DECLARED',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $approvalStatus = $app->isSensitivePermission($permission)
            ? 'pending'
            : 'auto_approved';

        $newId = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $user, $app, $companyId, $permission,
            $approvalStatus, $newId, $request
        ): void {
            $current = DB::table('user_app_permissions')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            DB::table('user_app_permissions')->insert([
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

            if ($current !== null) {
                DB::table('user_app_permissions')
                    ->where('id', $current->id)
                    ->update(['next_id' => $newId]);
            }
        });

        return response()->json(
            UserAppPermission::find($newId),
            Response::HTTP_CREATED
        );
    }

    public function revoke(Request $request, User $user, App $app, string $permission): JsonResponse
    {
        $this->authorize('permission.revoke');

        $companyId = $this->context->companyId();
        $newId     = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $user, $app, $companyId, $permission, $newId, $request
        ): void {
            $current = DB::table('user_app_permissions')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                abort(404, 'Permission not found.');
            }

            DB::table('user_app_permissions')->insert([
                'id'              => $newId,
                'user_id'         => $user->id,
                'company_id'      => $companyId,
                'app_id'          => $app->id,
                'permission'      => $permission,
                'granted'         => false,
                'granted_by'      => $request->user()->id,
                'approval_status' => 'auto_approved',
                'valid_from'      => now(),
                'valid_until'     => null,
                'version'         => $current->version + 1,
                'prev_id'         => $current->id,
                'next_id'         => null,
                'created_at'      => now(),
            ]);

            DB::table('user_app_permissions')
                ->where('id', $current->id)
                ->update(['next_id' => $newId]);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function history(User $user, App $app, string $permission): JsonResponse
    {
        $companyId = $this->context->companyId();

        $history = DB::table('user_app_permissions')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('app_id', $app->id)
            ->where('permission', $permission)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json($history);
    }

    public function rollback(Request $request, User $user, App $app, string $permissionId): JsonResponse
    {
        $this->authorize('permission.grant');

        $target    = UserAppPermission::findOrFail($permissionId);
        $companyId = $this->context->companyId();
        $newId     = (string) Str::orderedUuid();

        DB::transaction(function () use (
            $target, $user, $app, $companyId, $newId, $request
        ): void {
            $current = DB::table('user_app_permissions')
                ->where('user_id', $user->id)
                ->where('company_id', $companyId)
                ->where('app_id', $app->id)
                ->where('permission', $target->permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            DB::table('user_app_permissions')->insert([
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

            if ($current !== null) {
                DB::table('user_app_permissions')
                    ->where('id', $current->id)
                    ->update(['next_id' => $newId]);
            }
        });

        return response()->json(['message' => 'Permission rolled back successfully.']);
    }

    // ── Platform Permissions ─────────────────────────────────────────

    public function platformIndex(User $user): AnonymousResourceCollection
    {
        $permissions = UserPlatformPermission::where('user_id', $user->id)
            ->active()
            ->get();

        return \App\Http\Resources\V1\UserPlatformPermissionResource::collection($permissions);
    }

    public function grantPlatform(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'permission'  => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_.]+[a-z0-9]$/'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ]);

        $newId = (string) Str::orderedUuid();

        DB::transaction(function () use ($user, $request, $newId): void {
            $current = DB::table('user_platform_permissions')
                ->where('user_id', $user->id)
                ->where('permission', $request->permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            DB::table('user_platform_permissions')->insert([
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

            if ($current !== null) {
                DB::table('user_platform_permissions')
                    ->where('id', $current->id)
                    ->update(['next_id' => $newId]);
            }
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
            $current = DB::table('user_platform_permissions')
                ->where('user_id', $user->id)
                ->where('permission', $permission)
                ->whereNull('next_id')
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                abort(404, 'Permission not found.');
            }

            DB::table('user_platform_permissions')->insert([
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

            DB::table('user_platform_permissions')
                ->where('id', $current->id)
                ->update(['next_id' => $newId]);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
