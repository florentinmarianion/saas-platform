<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuditService
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function record(
        string  $action,
        string  $entityType,
        string  $entityId,
        array   $payload = [],
        ?string $companyId = null,
        ?string $appId = null,
    ): void {
        try {
            $request = app(Request::class);

            AuditLog::insert([
                'id'          => (string) Str::orderedUuid(),
                'user_id'     => $this->context->user()?->id,
                'company_id'  => $companyId ?? $this->context->companyId(),
                'app_id'      => $appId,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'payload'     => json_encode($payload, JSON_THROW_ON_ERROR),
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'request_id'  => $request->header('X-Request-ID', (string) Str::uuid()),
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit must never break the main flow
            // Log silently and continue
            logger()->error('AuditService failed: ' . $e->getMessage(), [
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
            ]);
        }
    }

    public function recordSystem(
        string $action,
        string $entityType,
        string $entityId,
        array  $payload = [],
    ): void {
        try {
            AuditLog::insert([
                'id'          => (string) Str::orderedUuid(),
                'user_id'     => null, // system action
                'company_id'  => null,
                'app_id'      => null,
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'payload'     => json_encode($payload, JSON_THROW_ON_ERROR),
                'ip_address'  => null,
                'user_agent'  => null,
                'request_id'  => null,
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('AuditService::recordSystem failed: ' . $e->getMessage());
        }
    }
}
