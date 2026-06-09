<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ApprovalRequestResource;
use App\Models\ApprovalRequest;
use App\Models\UserAppPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $approvals = ApprovalRequest::with(['requestedBy', 'reviewedBy'])
            ->when(
                $request->input('status', 'pending'),
                fn($q, $status) => $q->where('status', $status)
            )
            ->orderByDesc('created_at')
            ->paginate(25);

        return ApprovalRequestResource::collection($approvals);
    }

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        if ($approval->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending approvals can be approved.',
                'code'    => 'APPROVAL_NOT_PENDING',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Reviewer cannot be the same as requestor (Two-Man Rule)
        if ($approval->requested_by === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot approve your own request.',
                'code'    => 'SELF_APPROVAL_NOT_ALLOWED',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($approval, $request): void {
            $approval->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // Activate the underlying entity
            if ($approval->entity_type === 'UserAppPermission') {
                UserAppPermission::where('id', $approval->entity_id)
                    ->update([
                        'approval_status' => 'approved',
                        'approved_by'     => $request->user()->id,
                        'approved_at'     => now(),
                    ]);
            }
        });

        return response()->json(['message' => 'Approved successfully.']);
    }

    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($approval->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending approvals can be rejected.',
                'code'    => 'APPROVAL_NOT_PENDING',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($approval->requested_by === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot reject your own request.',
                'code'    => 'SELF_APPROVAL_NOT_ALLOWED',
            ], Response::HTTP_FORBIDDEN);
        }

        DB::transaction(function () use ($approval, $request): void {
            $approval->update([
                'status'           => 'rejected',
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
                'rejection_reason' => $request->reason,
            ]);

            // Mark the underlying permission as rejected
            if ($approval->entity_type === 'UserAppPermission') {
                UserAppPermission::where('id', $approval->entity_id)
                    ->update(['approval_status' => 'pending']); // stays pending
            }
        });

        return response()->json(['message' => 'Rejected successfully.']);
    }
}
