<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests that require a company context but don't have one.
 *
 * Applied on routes that operate within a company scope.
 * Super-admins bypass this check — they can operate without company context
 * for platform-level operations.
 *
 * Usage in routes:
 *   Route::middleware(['auth:sanctum', 'tenant', 'company'])->group(...)
 */
class EnsureCompanyContext
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->context->isSet()) {
            return response()->json([
                'message' => 'No active company context. Provide X-Company-ID header or use a company-scoped token.',
                'code'    => 'MISSING_COMPANY_CONTEXT',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify the company actually exists and is active
        if ($this->context->company() === null) {
            return response()->json([
                'message' => 'Company not found or suspended.',
                'code'    => 'INVALID_COMPANY_CONTEXT',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
