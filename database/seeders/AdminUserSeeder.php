<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Crează utilizatorul super-admin al platformei.
     *
     * Credentials pentru development/demo:
     *   Email:    admin@demo.com
     *   Password: password
     *
     * IMPORTANT: Schimbă credențialele ÎNAINTE de deploy în producție.
     * În producție, folosește variabile de mediu din .env.
     *
     * Super-admin-ul NU aparține niciunei companii inițial.
     * Permisiunile platform.* sunt acordate în PlatformPermissionsSeeder.
     */
    public function run(): void
    {
        $adminId = (string) Str::orderedUuid();

        DB::table('users')->insertOrIgnore([
            'id'           => $adminId,
            'email'        => env('ADMIN_EMAIL', 'admin@demo.com'),
            'name'         => env('ADMIN_NAME', 'Platform Administrator'),
            'password_hash' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'status'       => 'active',
            'locale'       => 'ro',
            'timezone'     => 'Europe/Bucharest',
            'version'      => 1,
            'prev_id'      => null,
            'next_id'      => null,
            'created_by'   => null, // auto-seeded
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // Stochează ID-ul pentru seeders următori
        // (PlatformPermissionsSeeder, DemoCompanySeeder)
        $this->command->getOutput()->writeln(
            "<info>Admin created:</info> {$adminId} / " . env('ADMIN_EMAIL', 'admin@demo.com')
        );
    }
}
