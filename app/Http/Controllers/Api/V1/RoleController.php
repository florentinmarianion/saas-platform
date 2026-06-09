<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RoleResource;
use App\Models\Role;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RoleController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $companyId = $this->context->companyId();

        $roles = $companyId !== null
            ? Role::scopeForCompany($request, $companyId)->paginate(25)
            : Role::platform()->paginate(25);

        return RoleResource::collection($roles);
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'                => ['required', 'string', 'max:100'],
            'slug'                => ['required', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'scope'               => ['sometimes', 'in:platform,company'],
            'default_permissions' => ['nullable', 'array'],
        ]);

        $role = Role::create([
            'id'                  => (string) Str::orderedUuid(),
            'name'                => $request->name,
            'slug'                => $request->slug,
            'description'         => $request->description,
            'scope'               => $request->scope ?? 'company',
            'company_id'          => $request->scope === 'platform'
                                        ? null
                                        : $this->context->companyId(),
            'default_permissions' => $request->default_permissions ?? [],
            'version'             => 1,
            'created_by'          => $request->user()->id,
        ]);

        return RoleResource::make($role)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, Role $role): RoleResource
    {
        $request->validate([
            'name'                => ['sometimes', 'string', 'max:100'],
            'description'         => ['nullable', 'string'],
            'default_permissions' => ['nullable', 'array'],
        ]);

        $role->update($request->only([
            'name', 'description', 'default_permissions',
        ]));

        return RoleResource::make($role->fresh());
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
