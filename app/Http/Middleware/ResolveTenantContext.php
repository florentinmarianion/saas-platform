<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant context from every authenticated request.
 *
 * Applied globally on all API routes via bootstrap/app.php.
 * Does NOT block requests without a company context — that is
 * the responsibility of EnsureCompanyContext middleware.
 */
class ResolveTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Only resolve if user is authenticated
        if ($request->user() !== null) {
            $this->context->resolveFromRequest($request);
        }

        return $next($request);
    }
}
