<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\InvitationResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class InvitationController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $invitations = Invitation::where('company_id', $this->context->companyId())
            ->with('invitedBy')
            ->orderByDesc('created_at')
            ->paginate(25);

        return InvitationResource::collection($invitations);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email'                => ['required', 'email', 'max:191'],
            'name'                 => ['nullable', 'string', 'max:255'],
            'role_label'           => ['nullable', 'string', 'max:100'],
            'permissions_snapshot' => ['nullable', 'array'],
            'expires_hours'        => ['nullable', 'integer', 'min:1', 'max:168'],
        ]);

        // Generate secure token — store only the hash
        $rawToken  = Str::random(64);
        $tokenHash = hash_hmac('sha256', $rawToken, config('app.key'));

        $invitation = Invitation::create([
            'id'                   => (string) Str::orderedUuid(),
            'company_id'           => $this->context->companyId(),
            'invited_by'           => $request->user()->id,
            'email'                => $request->email,
            'name'                 => $request->name,
            'token_hash'           => $tokenHash,
            'permissions_snapshot' => $request->permissions_snapshot ?? [],
            'role_label'           => $request->role_label,
            'status'               => 'pending',
            'expires_at'           => now()->addHours($request->expires_hours ?? 72),
        ]);

        // TODO: dispatch InvitationSent notification

        return InvitationResource::make($invitation)
            ->additional(['token' => $rawToken]) // raw token returned ONCE
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $token): JsonResponse
    {
        $tokenHash  = hash_hmac('sha256', $token, config('app.key'));
        $invitation = Invitation::where('token_hash', $tokenHash)->firstOrFail();

        if (!$invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is expired or already used.',
                'code'    => 'INVITATION_INVALID',
            ], Response::HTTP_GONE);
        }

        return InvitationResource::make($invitation->load('invitedBy'))
            ->response();
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $tokenHash  = hash_hmac('sha256', $token, config('app.key'));
        $invitation = Invitation::where('token_hash', $tokenHash)->firstOrFail();

        if (!$invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is expired or already used.',
                'code'    => 'INVITATION_INVALID',
            ], Response::HTTP_GONE);
        }

        // Find existing user or create new one
        $user = User::where('email', $invitation->email)->first();

        if ($user === null) {
            $request->validate([
                'password' => ['required', 'string', 'min:8'],
            ]);

            $user = User::create([
                'id'            => (string) Str::orderedUuid(),
                'email'         => $invitation->email,
                'name'          => $invitation->name ?? $request->input('name', 'User'),
                'password_hash' => $request->password,
                'status'        => 'active',
                'version'       => 1,
                'created_by'    => $invitation->invited_by,
            ]);
        }

        // Add user to company
        \App\Models\CompanyUser::insert([
            'id'              => (string) Str::orderedUuid(),
            'company_id'      => $invitation->company_id,
            'user_id'         => $user->id,
            'role_label'      => $invitation->role_label,
            'is_owner'        => false,
            'status'          => 'active',
            'approval_status' => 'approved',
            'approved_by'     => $invitation->invited_by,
            'approved_at'     => now(),
            'version'         => 1,
            'invited_by'      => $invitation->invited_by,
            'joined_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Mark invitation accepted
        $invitation->update([
            'status'      => 'accepted',
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);

        // Issue company-scoped token
        $sanctumToken = $user->createToken(
            name:      'invitation-accept',
            abilities: ["company:{$invitation->company_id}", 'api'],
        );

        return response()->json([
            'message' => 'Invitation accepted.',
            'token'   => $sanctumToken->plainTextToken,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function cancel(Invitation $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending invitations can be cancelled.',
                'code'    => 'INVITATION_NOT_PENDING',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $invitation->update(['status' => 'cancelled']);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
