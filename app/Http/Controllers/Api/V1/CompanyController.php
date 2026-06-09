<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Company\StoreCompanyRequest;
use App\Http\Requests\Api\V1\Company\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Resources\V1\CompanyResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);
        $companies = Company::current()
            ->when(
                $request->input('status'),
                fn($q, $status) => $q->where('status', $status)
            )
            ->when(
                $request->input('search'),
                fn($q, $search) => $q->where('name', 'like', "%{$search}%")
            )
            ->orderBy('name')
            ->paginate(25);

        return CompanyResource::collection($companies);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);
        $company = Company::create([
            'id'         => (string) Str::orderedUuid(),
            'slug'       => Str::slug($request->name),
            'name'       => $request->name,
            'legal_name' => $request->legal_name,
            'vat_number' => $request->vat_number,
            'country'    => $request->country ?? 'RO',
            'settings'   => $request->settings ?? [],
            'created_by' => $request->user()->id,
        ]);

        return CompanyResource::make($company)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Company $company): CompanyResource
    {
        $this->authorize('view', $company);
        return CompanyResource::make(
            $company->load(['users', 'apps'])
        );
    }

    public function update(UpdateCompanyRequest $request, Company $company): CompanyResource
    {
        $this->authorize('update', $company);
        $company->update($request->validated());

        return CompanyResource::make($company->fresh());
    }

    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);
        $company->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
