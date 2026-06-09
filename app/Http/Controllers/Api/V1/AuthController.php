<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\Company;
use App\Http\Resources\V1\CompanyBriefResource;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::active()
            ->where('email', $request->email)
            ->first();

        if ($user === null || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'message' => 'Invalid credentials.',
                'code'    => 'INVALID_CREDENTIALS',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Load companies the user belongs to
        $companies = $user->companies()
            ->wherePivot('status', 'active')
            ->get(['companies.id', 'companies.name', 'companies.slug']);

        // If user belongs to exactly one company, auto-scope the token
        $companyId = $companies->count() === 1
            ? $companies->first()->id
            : $request->input('company_id');

        $abilities = ['*'];

        if ($companyId !== null) {
            $abilities = ["company:{$companyId}", 'api'];
        }

        $token = $user->createToken(
            name:      $request->input('device_name', 'api-token'),
            abilities: $abilities,
        );

        return response()->json([
            'token'     => $token->plainTextToken,
            'user'      => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'locale'   => $user->locale,
                'timezone' => $user->timezone,
            ],
            'companies' => CompanyBriefResource::collection($companies),
            'company_id' => $companyId,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        $companies = $user->companies()
            ->wherePivot('status', 'active')
            ->get(['companies.id', 'companies.name', 'companies.slug']);

        return response()->json([
            'user'       => [
                'id'       => $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'locale'   => $user->locale,
                'timezone' => $user->timezone,
            ],
            'companies' => CompanyBriefResource::collection($companies),
            'company_id' => $this->context->companyId(),
        ]);
    }

    public function switchCompany(Request $request): JsonResponse
    {
        $request->validate([
            'company_id' => ['required', 'string', 'uuid'],
        ]);

        $user = $request->user();

        // Verify user belongs to requested company
        $membership = $user->companyUsers()
            ->where('company_id', $request->company_id)
            ->whereNull('next_id')
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            return response()->json([
                'message' => 'You do not have access to this company.',
                'code'    => 'COMPANY_ACCESS_DENIED',
            ], Response::HTTP_FORBIDDEN);
        }

        // Revoke current token and issue new company-scoped token
        $request->user()->currentAccessToken()->delete();

        $token = $user->createToken(
            name:      $request->input('device_name', 'api-token'),
            abilities: ["company:{$request->company_id}", 'api'],
        );

        return response()->json([
            'token'      => $token->plainTextToken,
            'company_id' => $request->company_id,
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        // Placeholder — implement with Laravel's password broker
        return response()->json(['message' => 'Password reset link sent.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        // Placeholder — implement with Laravel's password broker
        return response()->json(['message' => 'Password reset successfully.']);
    }
}
