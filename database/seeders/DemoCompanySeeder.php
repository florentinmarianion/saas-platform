<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoCompanySeeder extends Seeder
{
    /**
     * Creează scenariul demo complet:
     *
     * Company:  Demo SRL
     * Users:
     *   - admin@demo.com      → owner al companiei, toate permisiunile
     *   - member@demo.com     → member, acces limitat la CurrencyExchange
     *   - manager@demo.com    → manager, acces CurrencyExchange + Accounting (read)
     *
     * Apps activate pentru Demo SRL:
     *   - currency-exchange   (active)
     *   - accounting          (active)
     *
     * Permisiuni demo:
     *   - member → currency-exchange: view, convert
     *   - manager → currency-exchange: view, convert, export, history.view
     *   - manager → accounting: view, reports.view
     *   - admin → toate permisiunile la toate app-urile activate
     */
    public function run(): void
    {
        $now = now();

        // ── 1. Fetch admin existent ──────────────────────────────────
        $admin = DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@demo.com'))
            ->first();

        if ($admin === null) {
            $this->command->error('Admin user not found.');
            return;
        }

        // ── 2. Fetch app IDs ─────────────────────────────────────────
        $currencyApp    = DB::table('apps')->where('slug', 'currency-exchange')->first();
        $accountingApp  = DB::table('apps')->where('slug', 'accounting')->first();

        if ($currencyApp === null || $accountingApp === null) {
            $this->command->error('Apps not found. Run AppsSeeder first.');
            return;
        }

        // ── 3. Crează compania Demo SRL ──────────────────────────────
        $companyId = (string) Str::orderedUuid();

        DB::table('companies')->insertOrIgnore([
            'id'              => $companyId,
            'slug'            => 'demo-srl',
            'name'            => 'Demo SRL',
            'legal_name'      => 'Demo Software Solutions SRL',
            'vat_number'      => 'RO12345678',
            'country'         => 'RO',
            'settings'        => json_encode([
                'plan'         => 'demo',
                'max_users'    => 10,
                'features'     => ['currency-exchange', 'accounting'],
                'locale'       => 'ro',
                'timezone'     => 'Europe/Bucharest',
            ]),
            'status'          => 'active',
            'approval_status' => 'approved',
            'approved_by'     => $admin->id,
            'approved_at'     => $now,
            'version'         => 1,
            'prev_id'         => null,
            'next_id'         => null,
            'created_by'      => $admin->id,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);

        // ── 4. Crează useri demo ─────────────────────────────────────
        $memberId  = (string) Str::orderedUuid();
        $managerId = (string) Str::orderedUuid();

        DB::table('users')->insertOrIgnore([
            [
                'id'           => $memberId,
                'email'        => 'member@demo.com',
                'name'         => 'Demo Member',
                'password_hash' => Hash::make('password'),
                'status'       => 'active',
                'locale'       => 'ro',
                'timezone'     => 'Europe/Bucharest',
                'version'      => 1,
                'prev_id'      => null,
                'next_id'      => null,
                'created_by'   => $admin->id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
            [
                'id'           => $managerId,
                'email'        => 'manager@demo.com',
                'name'         => 'Demo Manager',
                'password_hash' => Hash::make('password'),
                'status'       => 'active',
                'locale'       => 'ro',
                'timezone'     => 'Europe/Bucharest',
                'version'      => 1,
                'prev_id'      => null,
                'next_id'      => null,
                'created_by'   => $admin->id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ],
        ]);

        // ── 5. Asociază userii în companie ───────────────────────────
        $memberships = [
            [
                'id'              => (string) Str::orderedUuid(),
                'company_id'      => $companyId,
                'user_id'         => $admin->id,
                'role_label'      => 'Administrator',
                'is_owner'        => true,
                'status'          => 'active',
                'approval_status' => 'approved',
                'approved_by'     => $admin->id,
                'approved_at'     => $now,
                'version'         => 1,
                'invited_by'      => null,
                'joined_at'       => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::orderedUuid(),
                'company_id'      => $companyId,
                'user_id'         => $memberId,
                'role_label'      => 'Member',
                'is_owner'        => false,
                'status'          => 'active',
                'approval_status' => 'approved',
                'approved_by'     => $admin->id,
                'approved_at'     => $now,
                'version'         => 1,
                'invited_by'      => $admin->id,
                'joined_at'       => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::orderedUuid(),
                'company_id'      => $companyId,
                'user_id'         => $managerId,
                'role_label'      => 'Manager',
                'is_owner'        => false,
                'status'          => 'active',
                'approval_status' => 'approved',
                'approved_by'     => $admin->id,
                'approved_at'     => $now,
                'version'         => 1,
                'invited_by'      => $admin->id,
                'joined_at'       => $now,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ];

        DB::table('company_users')->insertOrIgnore($memberships);

        // ── 6. Activează apps pentru companie ────────────────────────
        DB::table('company_apps')->insertOrIgnore([
            [
                'id'              => (string) Str::orderedUuid(),
                'company_id'      => $companyId,
                'app_id'          => $currencyApp->id,
                'status'          => 'active',
                'config'          => json_encode([]),
                'approval_status' => 'auto_approved',
                'activated_by'    => $admin->id,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
            [
                'id'              => (string) Str::orderedUuid(),
                'company_id'      => $companyId,
                'app_id'          => $accountingApp->id,
                'status'          => 'active',
                'config'          => json_encode([]),
                'approval_status' => 'auto_approved',
                'activated_by'    => $admin->id,
                'created_at'      => $now,
                'updated_at'      => $now,
            ],
        ]);

        // ── 7. Acordă permisiuni per app ─────────────────────────────
        $permissions = $this->buildPermissions(
            companyId:     $companyId,
            adminId:       $admin->id,
            memberId:      $memberId,
            managerId:     $managerId,
            currencyAppId: $currencyApp->id,
            accountingAppId: $accountingApp->id,
            now:           $now,
        );

        DB::table('user_app_permissions')->insertOrIgnore($permissions);

        // ── Output ───────────────────────────────────────────────────
        $this->command->getOutput()->writeln('<info>Demo scenario created:</info>');
        $this->command->getOutput()->writeln('  Company:  Demo SRL');
        $this->command->getOutput()->writeln('  Users:    admin@demo.com | member@demo.com | manager@demo.com');
        $this->command->getOutput()->writeln('  Password: password (toți)');
        $this->command->getOutput()->writeln('  Apps:     currency-exchange, accounting');
    }

    private function buildPermissions(
        string $companyId,
        string $adminId,
        string $memberId,
        string $managerId,
        string $currencyAppId,
        string $accountingAppId,
        mixed  $now,
    ): array {
        $rows = [];

        // Helper closure pentru a crea un rând de permisiune
        $perm = function (
            string $userId,
            string $appId,
            string $permission,
            bool   $granted = true,
            string $approval = 'auto_approved',
        ) use ($companyId, $adminId, $now): array {
            return [
                'id'              => (string) Str::orderedUuid(),
                'user_id'         => $userId,
                'company_id'      => $companyId,
                'app_id'          => $appId,
                'permission'      => $permission,
                'granted'         => $granted,
                'granted_by'      => $adminId,
                'approved_by'     => $approval === 'approved' ? $adminId : null,
                'approved_at'     => $approval === 'approved' ? $now : null,
                'approval_status' => $approval,
                'valid_from'      => $now,
                'valid_until'     => null,
                'version'         => 1,
                'prev_id'         => null,
                'next_id'         => null,
                'created_at'      => $now,
            ];
        };

        // Admin → currency-exchange (toate)
        foreach (['view', 'convert', 'export', 'rates.manage', 'history.view'] as $p) {
            $rows[] = $perm($adminId, $currencyAppId, $p);
        }

        // Admin → accounting (toate, sensitive cu approval explicit)
        foreach ([
            'view', 'entries.create', 'entries.edit', 'entries.delete',
            'invoices.create', 'invoices.edit', 'reports.view', 'reports.export',
            'budget.manage', 'settings.manage',
        ] as $p) {
            $rows[] = $perm($adminId, $accountingAppId, $p);
        }
        // Permisiunile sensibile primesc 'approved' explicit (simulate Two-Man Rule în seed)
        foreach (['invoices.approve', 'invoices.void', 'salary.view'] as $p) {
            $rows[] = $perm($adminId, $accountingAppId, $p, true, 'approved');
        }

        // Member → currency-exchange (view + convert)
        foreach (['view', 'convert'] as $p) {
            $rows[] = $perm($memberId, $currencyAppId, $p);
        }

        // Manager → currency-exchange (view + convert + export + history)
        foreach (['view', 'convert', 'export', 'history.view'] as $p) {
            $rows[] = $perm($managerId, $currencyAppId, $p);
        }

        // Manager → accounting (view + reports, fără date financiare sensibile)
        foreach (['view', 'reports.view'] as $p) {
            $rows[] = $perm($managerId, $accountingAppId, $p);
        }

        return $rows;
    }
}
