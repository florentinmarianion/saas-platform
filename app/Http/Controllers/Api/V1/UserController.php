<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    // ── Platform level ───────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::current()
            ->when(
                $request->input('status'),
                fn($q, $status) => $q->where('status', $status)
            )
            ->when(
                $request->input('search'),
                fn($q, $search) => $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate(25);

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return UserResource::make(
            $user->load('companies')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'id'            => (string) \Illuminate\Support\Str::orderedUuid(),
            'name'          => $request->name,
            'email'         => $request->email,
            'password_hash' => $request->password,
            'locale'        => $request->locale ?? 'ro',
            'timezone'      => $request->timezone ?? 'Europe/Bucharest',
            'status'        => 'active',
            'version'       => 1,
            'created_by'    => $request->user()->id,
        ]);

        return UserResource::make($user)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user): UserResource
    {
        $request->validate([
            'name'     => ['sometimes', 'string', 'max:255'],
            'locale'   => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($request->only(['name', 'locale', 'timezone']));

        return UserResource::make($user->fresh());
    }

    public function destroy(User $user): JsonResponse
    {
        $user->update(['status' => 'deleted']);
        $user->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function updateStatus(Request $request, User $user): UserResource
    {
        $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $user->update(['status' => $request->status]);

        return UserResource::make($user->fresh());
    }

    // ── Company level ────────────────────────────────────────────────

    public function companyIndex(Request $request): AnonymousResourceCollection
    {
        $companyId = $this->context->companyId();

        $users = User::current()
            ->whereHas('companyUsers', function ($q) use ($companyId): void {
                $q->where('company_id', $companyId)
                  ->whereNull('next_id')
                  ->where('status', 'active');
            })
            ->when(
                $request->input('search'),
                fn($q, $search) => $q->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
            )
            ->orderBy('name')
            ->paginate(25);

        return UserResource::collection($users);
    }

    public function removeFromCompany(Request $request, User $user): JsonResponse
    {
        $companyId = $this->context->companyId();

        $membership = $user->companyUsers()
            ->where('company_id', $companyId)
            ->whereNull('next_id')
            ->firstOrFail();

        // Cannot remove company owner
        if ($membership->is_owner) {
            return response()->json([
                'message' => 'Cannot remove company owner. Transfer ownership first.',
                'code'    => 'CANNOT_REMOVE_OWNER',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $membership->update(['status' => 'removed']);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function updateCompanyStatus(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:active,suspended'],
        ]);

        $companyId = $this->context->companyId();

        $membership = $user->companyUsers()
            ->where('company_id', $companyId)
            ->whereNull('next_id')
            ->firstOrFail();

        $membership->update(['status' => $request->status]);

        return response()->json(['status' => $request->status]);
    }
}
