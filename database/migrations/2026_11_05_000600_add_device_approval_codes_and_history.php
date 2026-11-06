<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Same model as ROVEXY: a short match code on the phone + an append-only approval history per device.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $t) {
            $t->string('approval_code', 9)->nullable(); // shown on the phone while pending; NOT a secret
        });
        Schema::create('device_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->constrained()->cascadeOnDelete();
            $t->string('action', 32); // registered|re_registered|replacement_requested|approved|rejected|revoked|replaced
            $t->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reason')->nullable();
            $t->json('meta')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['device_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_events');
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn('approval_code'));
    }
};
