<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')->where('name', 'Cong Staff')->update(['name' => 'Staff']);
        DB::table('users')->where('role_name', 'Cong Staff')->update(['role_name' => 'Staff']);
        DB::table('permissions')->where('role_name', 'Cong Staff')->update(['role_name' => 'Staff']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->where('name', 'Staff')->update(['name' => 'Cong Staff']);
        DB::table('users')->where('role_name', 'Staff')->update(['role_name' => 'Cong Staff']);
        DB::table('permissions')->where('role_name', 'Staff')->update(['role_name' => 'Cong Staff']);
    }
};