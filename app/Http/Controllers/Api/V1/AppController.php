<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AppResource;
use App\Models\App;
use App\Models\CompanyApp;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AppController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    // ── Platform level ───────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $apps = App::current()
            ->when(
                $request->input('status'),
                fn($q, $status) => $q->where('status', $status)
            )
            ->orderBy('name')
            ->paginate(25);

        return AppResource::collection($apps);
    }

    public function show(App $app): AppResource
    {
        return AppResource::make($app);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slug'                 => ['required', 'string', 'max:100'],
            'module'               => ['required', 'string', 'max:100'],
            'name'                 => ['required', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'icon'                 => ['nullable', 'string', 'max:100'],
            'semver'               => ['nullable', 'string', 'max:20'],
            'declared_permissions' => ['nullable', 'array'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $app = App::create([
            'id'                   => (string) Str::orderedUuid(),
            'slug'                 => $request->slug,
            'module'               => $request->module,
            'name'                 => $request->name,
            'description'          => $request->description,
            'icon'                 => $request->icon,
            'semver'               => $request->semver ?? '1.0.0',
            'declared_permissions' => $request->declared_permissions ?? [],
            'metadata'             => $request->metadata ?? [],
            'status'               => 'active',
            'version'              => 1,
            'created_by'           => $request->user()->id,
        ]);

        return AppResource::make($app)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(Request $request, App $app): AppResource
    {
        $request->validate([
            'name'                 => ['sometimes', 'string', 'max:255'],
            'description'          => ['nullable', 'string'],
            'status'               => ['sometimes', 'in:active,beta,deprecated,disabled'],
            'declared_permissions' => ['nullable', 'array'],
            'metadata'             => ['nullable', 'array'],
        ]);

        $app->update($request->only([
            'name', 'description', 'status',
            'declared_permissions', 'metadata',
        ]));

        return AppResource::make($app->fresh());
    }

    public function destroy(App $app): JsonResponse
    {
        $app->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    // ── Company level ────────────────────────────────────────────────

    public function companyIndex(): AnonymousResourceCollection
    {
        $companyId = $this->context->companyId();

        $apps = App::current()
            ->whereHas('companyApps', fn($q) => $q->where('company_id', $companyId)
                ->where('status', 'active'))
            ->get();

        return AppResource::collection($apps);
    }

    public function activate(Request $request, App $app): JsonResponse
    {
        $companyId = $this->context->companyId();

        $existing = CompanyApp::where('company_id', $companyId)
            ->where('app_id', $app->id)
            ->first();

        if ($existing !== null) {
            $existing->update(['status' => 'active']);

            return response()->json(['message' => 'App activated.']);
        }

        CompanyApp::insert([
            'id'              => (string) Str::orderedUuid(),
            'company_id'      => $companyId,
            'app_id'          => $app->id,
            'status'          => 'active',
            'config'          => json_encode([]),
            'approval_status' => 'auto_approved',
            'activated_by'    => $request->user()->id,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return response()->json(['message' => 'App activated.'], Response::HTTP_CREATED);
    }

    public function deactivate(App $app): JsonResponse
    {
        $companyId = $this->context->companyId();

        CompanyApp::where('company_id', $companyId)
            ->where('app_id', $app->id)
            ->update(['status' => 'suspended']);

        return response()->json(['message' => 'App deactivated.']);
    }
}
