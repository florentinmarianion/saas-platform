<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppsSeeder extends Seeder
{
    /**
     * Înregistrează toate aplicațiile disponibile pe platformă.
     *
     * 'declared_permissions' = lista completă de permisiuni pe care
     * modulul le poate acorda. Folosită pentru validare la runtime:
     * dacă cineva încearcă să acorde o permisiune nedeclarată → reject.
     *
     * 'metadata' = configurație tehnică a modulului:
     *   - min_php: versiunea minimă PHP
     *   - requires: alte module necesare
     *   - billing_model: 'flat', 'per_user', 'usage'
     *   - sensitive_permissions: permisiuni care necesită Two-Man Rule
     */
    public function run(): void
    {
        $now  = now();
        $apps = $this->appDefinitions();

        foreach ($apps as $app) {
            DB::table('apps')->insertOrIgnore([
                'id'                   => Str::orderedUuid(),
                'slug'                 => $app['slug'],
                'module'               => $app['module'],
                'name'                 => $app['name'],
                'description'          => $app['description'],
                'icon'                 => $app['icon'],
                'semver'               => $app['semver'],
                'declared_permissions' => json_encode($app['declared_permissions']),
                'metadata'             => json_encode($app['metadata']),
                'status'               => $app['status'],
                'approval_status'      => 'approved',
                'version'              => 1,
                'prev_id'              => null,
                'next_id'              => null,
                'created_by'           => null, // seeder = system
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    private function appDefinitions(): array
    {
        return [
            [
                'slug'        => 'currency-exchange',
                'module'      => 'CurrencyExchange',
                'name'        => 'Currency Exchange',
                'description' => 'Cursuri valutare BNR, convertor și istoric. Date în timp real cu cache inteligent.',
                'icon'        => 'currency-exchange',
                'semver'      => '1.0.0',
                'status'      => 'active',
                'declared_permissions' => [
                    'view',           // acces de bază la modul
                    'convert',        // folosire convertor
                    'export',         // export CSV/PDF
                    'rates.manage',   // administrare cursuri manuale (override BNR)
                    'history.view',   // acces la istoricul cursurilor
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => [],
                    'billing_model'       => 'flat',
                    'sensitive_permissions' => [], // nicio permisiune nu necesită Two-Man Rule
                    'data_source'         => 'curs.bnr.ro',
                    'cache_strategy'      => 'redis',
                ],
            ],
            [
                'slug'        => 'accounting',
                'module'      => 'Accounting',
                'name'        => 'Accounting',
                'description' => 'Contabilitate primară, facturi, balanțe și rapoarte financiare.',
                'icon'        => 'accounting',
                'semver'      => '0.1.0',
                'status'      => 'beta',
                'declared_permissions' => [
                    'view',
                    'entries.create',
                    'entries.edit',
                    'entries.delete',
                    'invoices.create',
                    'invoices.edit',
                    'invoices.approve',   // ← Two-Man Rule
                    'invoices.void',      // ← Two-Man Rule
                    'reports.view',
                    'reports.export',
                    'salary.view',        // ← Two-Man Rule (date sensibile)
                    'budget.manage',
                    'settings.manage',
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => [],
                    'billing_model'       => 'per_user',
                    'sensitive_permissions' => [
                        'invoices.approve',
                        'invoices.void',
                        'salary.view',
                    ],
                ],
            ],
            [
                'slug'        => 'hr',
                'module'      => 'HR',
                'name'        => 'HR Management',
                'description' => 'Gestionare angajați, contracte, pontaje și concedii.',
                'icon'        => 'hr',
                'semver'      => '0.1.0',
                'status'      => 'beta',
                'declared_permissions' => [
                    'view',
                    'employees.create',
                    'employees.edit',
                    'employees.terminate',   // ← Two-Man Rule
                    'contracts.view',
                    'contracts.create',
                    'contracts.approve',     // ← Two-Man Rule
                    'salary.view',           // ← Two-Man Rule
                    'salary.manage',         // ← Two-Man Rule
                    'timesheet.view',
                    'timesheet.approve',
                    'leave.approve',
                    'reports.view',
                    'reports.export',
                    'settings.manage',
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => [],
                    'billing_model'       => 'per_user',
                    'sensitive_permissions' => [
                        'employees.terminate',
                        'contracts.approve',
                        'salary.view',
                        'salary.manage',
                    ],
                ],
            ],
            [
                'slug'        => 'beauty-salon',
                'module'      => 'BeautySalon',
                'name'        => 'Beauty Salon',
                'description' => 'Programări, servicii, personal și gestiune salon de frumusețe.',
                'icon'        => 'beauty-salon',
                'semver'      => '0.1.0',
                'status'      => 'beta',
                'declared_permissions' => [
                    'view',
                    'appointments.create',
                    'appointments.edit',
                    'appointments.cancel',
                    'services.manage',
                    'staff.manage',
                    'clients.view',
                    'clients.manage',
                    'reports.view',
                    'reports.export',
                    'settings.manage',
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => [],
                    'billing_model'       => 'flat',
                    'sensitive_permissions' => [],
                ],
            ],
            [
                'slug'        => 'forex',
                'module'      => 'Forex',
                'name'        => 'Forex Trading',
                'description' => 'Tranzacționare valutară în timp real cu date live de piață.',
                'icon'        => 'forex',
                'semver'      => '0.1.0',
                'status'      => 'beta',
                'declared_permissions' => [
                    'view',
                    'quotes.view',
                    'orders.create',      // ← Two-Man Rule
                    'orders.cancel',      // ← Two-Man Rule
                    'live.trade',         // ← Two-Man Rule (risc financiar maxim)
                    'reports.view',
                    'reports.export',
                    'settings.manage',
                    'risk.manage',        // ← Two-Man Rule
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => ['currency-exchange'],
                    'billing_model'       => 'usage',
                    'sensitive_permissions' => [
                        'orders.create',
                        'orders.cancel',
                        'live.trade',
                        'risk.manage',
                    ],
                ],
            ],
            [
                'slug'        => 'reports',
                'module'      => 'Reports',
                'name'        => 'Reports & Analytics',
                'description' => 'Rapoarte consolidate și analytics cross-module.',
                'icon'        => 'reports',
                'semver'      => '0.1.0',
                'status'      => 'beta',
                'declared_permissions' => [
                    'view',
                    'reports.view',
                    'reports.create',
                    'reports.export',
                    'reports.schedule',
                    'dashboard.customize',
                    'settings.manage',
                ],
                'metadata' => [
                    'min_php'             => '8.5',
                    'requires'            => [],
                    'billing_model'       => 'flat',
                    'sensitive_permissions' => [],
                ],
            ],
        ];
    }
}
