<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->string('shift_start', 5)->default('09:30');           // office day starts (local time)
            $t->unsignedSmallInteger('late_after_min')->default(10);  // first IN later than shift_start + this = late
            $t->json('role_permissions')->nullable();                 // null = defaults (App\Support\Permissions)
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', fn (Blueprint $t) => $t->dropColumn(['shift_start', 'late_after_min', 'role_permissions']));
    }
};
