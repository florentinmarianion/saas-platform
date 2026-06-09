<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformPermissionsSeeder extends Seeder
{
    /**
     * Acordă toate permisiunile de platformă super-adminului.
     *
     * Permisiuni de platformă (prefix: platform.):
     *
     * companies.*  → gestiune companii
     * users.*      → gestiune utilizatori globali
     * apps.*       → gestiune aplicații (înregistrare, aprobare)
     * audit.*      → acces la log-ul de audit
     * billing.*    → gestiune billing/subscripții
     * approvals.*  → poate aproba orice ApprovalRequest
     * platform.*   → wildcard (implicit toate de mai sus)
     *
     * Notă: în runtime, verificăm mai întâi 'platform.*' (wildcard),
     * apoi permisiunea specifică. Super-admin-ul primește wildcard.
     */
    public function run(): void
    {
        $admin = DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@demo.com'))
            ->first();

        if ($admin === null) {
            $this->command->error('Admin user not found. Run AdminUserSeeder first.');
            return;
        }

        $platformPermissions = [
            // Wildcard — acces total la toate funcțiile platformei
            'platform.*',

            // Explicite (pentru claritate în UI și audit)
            'platform.companies.create',
            'platform.companies.edit',
            'platform.companies.delete',
            'platform.companies.manage_all',

            'platform.users.create',
            'platform.users.edit',
            'platform.users.delete',
            'platform.users.suspend',
            'platform.users.manage',

            'platform.apps.register',
            'platform.apps.approve',
            'platform.apps.manage',

            'platform.audit.read',
            'platform.audit.export',

            'platform.billing.view',
            'platform.billing.manage',

            'platform.approvals.review',
            'platform.approvals.override',

            'platform.settings.manage',
        ];

        $now = now();

        foreach ($platformPermissions as $permission) {
            DB::table('user_platform_permissions')->insertOrIgnore([
                'id'              => (string) Str::orderedUuid(),
                'user_id'         => $admin->id,
                'permission'      => $permission,
                'granted'         => true,
                'approval_status' => 'auto_approved',
                'granted_by'      => $admin->id, // self-granted la seed
                'approved_by'     => null,
                'valid_until'     => null,        // permanent
                'version'         => 1,
                'prev_id'         => null,
                'next_id'         => null,
                'created_at'      => $now,
            ]);
        }

        $count = count($platformPermissions);
        $this->command->getOutput()->writeln(
            "<info>Platform permissions seeded:</info> {$count} permissions → admin"
        );
    }
}
