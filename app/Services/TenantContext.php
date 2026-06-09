<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;

/**
 * Singleton service that holds the active tenant context for the current request.
 *
 * Replaces session('active_company_id') entirely.
 * Bound as a singleton in TenantServiceProvider.
 *
 * Resolution priority:
 *   1. X-Company-ID header (API requests)
 *   2. Sanctum token ability "company:{uuid}" (token-scoped requests)
 *   3. Not set — platform-level requests (super-admin, no company context)
 */
final class TenantContext
{
    private ?string  $companyId = null;
    private ?Company $company   = null;
    private ?User    $user      = null;

    // ── Setters ──────────────────────────────────────────────────────

    public function setCompany(Company $company): void
    {
        $this->company   = $company;
        $this->companyId = $company->id;
    }

    public function setCompanyId(string $companyId): void
    {
        $this->companyId = $companyId;
        $this->company   = null; // lazy load on first access
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    // ── Getters ──────────────────────────────────────────────────────

    public function companyId(): ?string
    {
        return $this->companyId;
    }

    public function company(): ?Company
    {
        if ($this->company === null && $this->companyId !== null) {
            $this->company = Company::withoutGlobalScopes()
                ->where('id', $this->companyId)
                ->where('status', 'active')
                ->first();
        }

        return $this->company;
    }

    public function user(): ?User
    {
        return $this->user;
    }

    // ── State checks ─────────────────────────────────────────────────

    public function isSet(): bool
    {
        return $this->companyId !== null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->user !== null
            && $this->user->platformPermissions()
                          ->where('permission', 'platform.*')
                          ->exists();
    }

    // ── Resolution from request ───────────────────────────────────────

    public function resolveFromRequest(\Illuminate\Http\Request $request): void
    {
        // Priority 1: explicit header (API clients send this)
        $companyId = $request->header('X-Company-ID');

        // Priority 2: Sanctum token ability "company:{uuid}"
        if ($companyId === null && $request->user() !== null) {
            $token = $request->user()->currentAccessToken();

            if ($token !== null) {
                $abilities = $token->abilities ?? [];
                foreach ($abilities as $ability) {
                    if (str_starts_with($ability, 'company:')) {
                        $companyId = substr($ability, 8);
                        break;
                    }
                }
            }
        }

        if ($companyId !== null) {
            $this->setCompanyId($companyId);
        }

        if ($request->user() instanceof User) {
            $this->setUser($request->user());
        }
    }

    // ── Reset (useful for testing) ────────────────────────────────────

    public function reset(): void
    {
        $this->companyId = null;
        $this->company   = null;
        $this->user      = null;
    }
}
