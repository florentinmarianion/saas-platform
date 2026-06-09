<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Company;
use App\Services\AuditService;

class CompanyObserver
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    public function created(Company $company): void
    {
        $this->audit->record(
            action:     'company.created',
            entityType: 'Company',
            entityId:   $company->id,
            payload:    [
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            companyId: $company->id,
        );
    }

    public function updated(Company $company): void
    {
        $changes = $company->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        $this->audit->record(
            action:     'company.updated',
            entityType: 'Company',
            entityId:   $company->id,
            payload:    [
                'changes'  => $changes,
                'original' => array_intersect_key(
                    $company->getOriginal(),
                    $changes,
                ),
            ],
            companyId: $company->id,
        );
    }

    public function deleted(Company $company): void
    {
        $this->audit->record(
            action:     'company.deleted',
            entityType: 'Company',
            entityId:   $company->id,
            payload:    ['name' => $company->name],
            companyId:  $company->id,
        );
    }

    public function restored(Company $company): void
    {
        $this->audit->record(
            action:     'company.restored',
            entityType: 'Company',
            entityId:   $company->id,
            payload:    ['name' => $company->name],
            companyId:  $company->id,
        );
    }
}
