<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Introduce the default "Viewer" role for new users:
     *  - register the role so it appears in management screens,
     *  - reassign users that never got a role (a blank role silently
     *    granted full access) to the least-privileged Viewer role,
     *  - backfill a deny-by-default permission row per feature so a
     *    Viewer is never granted access through a missing row.
     */
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'Viewer')->doesntExist()) {
            DB::table('roles')->insert([
                'name' => 'Viewer',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNull('role_name')
            ->orWhere('role_name', '')
            ->update(['role_name' => 'Viewer']);

        $features = DB::table('permissions')
            ->distinct()
            ->pluck('feature')
            ->all();

        $features = array_values(array_unique(array_merge($features, ['Dashboard'])));

        $existing = DB::table('permissions')
            ->where('role_name', 'Viewer')
            ->pluck('feature')
            ->all();

        foreach ($features as $feature) {
            if (in_array($feature, $existing, true)) {
                continue;
            }

            DB::table('permissions')->insert([
                'feature' => $feature,
                'role_name' => 'Viewer',
                'allowed' => $feature === 'Dashboard',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role_name', 'Viewer')->update(['role_name' => '']);
        DB::table('permissions')->where('role_name', 'Viewer')->delete();
        DB::table('roles')->where('name', 'Viewer')->delete();
    }
};