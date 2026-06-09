<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AuditLogResource;
use App\Models\AuditLog;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    // Platform-level audit — all actions
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = AuditLog::with('user')
            ->when(
                $request->input('action'),
                fn($q, $action) => $q->where('action', $action)
            )
            ->when(
                $request->input('user_id'),
                fn($q, $userId) => $q->where('user_id', $userId)
            )
            ->when(
                $request->input('entity_type'),
                fn($q, $type) => $q->where('entity_type', $type)
            )
            ->orderByDesc('created_at')
            ->paginate(50);

        return AuditLogResource::collection($logs);
    }

    // Company-scoped audit
    public function companyIndex(Request $request): AnonymousResourceCollection
    {
        $logs = AuditLog::with('user')
            ->where('company_id', $this->context->companyId())
            ->when(
                $request->input('action'),
                fn($q, $action) => $q->where('action', $action)
            )
            ->orderByDesc('created_at')
            ->paginate(50);

        return AuditLogResource::collection($logs);
    }
}
