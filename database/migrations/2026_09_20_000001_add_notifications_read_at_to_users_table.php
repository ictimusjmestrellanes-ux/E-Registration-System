<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user navbar notification read marker (highest activity_log id
     * seen). "Mark all as read" stamps this; anything logged afterwards
     * (higher id) counts as unread. Id-based so same-second updates can
     * never be missed by timestamp precision.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('notifications_read_id')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_read_id');
        });
    }
};
