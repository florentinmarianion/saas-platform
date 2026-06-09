<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    /**
     * Seeds platform-level roles.
     *
     * These are the default roles available to all companies.
     * Companies can create their own custom roles (scope: company) from the UI.
     *
     * Remember: roles are templates only.
     * The default_permissions are copied at invite time — never evaluated at runtime.
     */
    public function run(): void
    {
        $now   = now();
        $roles = $this->roleDefinitions();

        foreach ($roles as $role) {
            DB::table('roles')->insertOrIgnore([
                'id'                  => (string) Str::orderedUuid(),
                'name'                => $role['name'],
                'slug'                => $role['slug'],
                'description'         => $role['description'],
                'scope'               => 'platform',
                'company_id'          => null,
                'default_permissions' => json_encode($role['default_permissions']),
                'version'             => 1,
                'prev_id'             => null,
                'next_id'             => null,
                'created_by'          => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        $this->command->getOutput()->writeln(
            '<info>Roles seeded:</info> ' . count($roles) . ' platform roles'
        );
    }

    private function roleDefinitions(): array
    {
        return [
            [
                'name'        => 'Admin',
                'slug'        => 'admin',
                'description' => 'Full access to all company apps and settings. Platform permissions bypass applies separately.',
                'default_permissions' => [
                    'platform' => [],
                    'apps'     => [
                        'currency-exchange' => ['view', 'convert', 'export', 'rates.manage', 'history.view'],
                        'accounting'        => ['view', 'entries.create', 'entries.edit', 'entries.delete',
                                               'invoices.create', 'invoices.edit', 'reports.view', 'reports.export',
                                               'budget.manage', 'settings.manage'],
                        'hr'                => ['view', 'employees.create', 'employees.edit', 'contracts.view',
                                               'contracts.create', 'timesheet.view', 'timesheet.approve',
                                               'leave.approve', 'reports.view', 'settings.manage'],
                        'beauty-salon'      => ['view', 'appointments.create', 'appointments.edit',
                                               'appointments.cancel', 'services.manage', 'staff.manage',
                                               'clients.view', 'clients.manage', 'reports.view', 'settings.manage'],
                        'forex'             => ['view', 'quotes.view', 'reports.view', 'settings.manage'],
                        'reports'           => ['view', 'reports.view', 'reports.create', 'reports.export',
                                               'reports.schedule', 'dashboard.customize', 'settings.manage'],
                    ],
                ],
            ],
            [
                'name'        => 'Member',
                'slug'        => 'member',
                'description' => 'Basic read access. Permissions are extended manually per user.',
                'default_permissions' => [
                    'platform' => [],
                    'apps'     => [
                        'currency-exchange' => ['view', 'convert'],
                        'accounting'        => ['view'],
                        'hr'                => ['view'],
                        'beauty-salon'      => ['view', 'appointments.create'],
                        'forex'             => ['view', 'quotes.view'],
                        'reports'           => ['view', 'reports.view'],
                    ],
                ],
            ],
        ];
    }
}
