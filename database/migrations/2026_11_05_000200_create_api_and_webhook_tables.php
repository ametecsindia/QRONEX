<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stage 6: public API keys, request log, idempotency, outbound webhooks.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $t) {
            $t->boolean('api_enabled')->nullable(); // null = follow plan (plan_versions.api_included)
        });

        Schema::create('api_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('name', 80);
            $t->string('prefix', 20)->unique(); // shown in UI, e.g. qrx_live_7f3a9c2e
            $t->string('key_hash', 64)->unique();
            $t->json('scopes');
            $t->unsignedSmallInteger('rate_limit_per_min')->default(120);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('api_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('api_key_id')->nullable()->constrained()->nullOnDelete();
            $t->string('method', 8);
            $t->string('path', 190);
            $t->unsignedSmallInteger('status');
            $t->unsignedInteger('ms');
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('idempotency_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $t->string('key', 100);
            $t->string('request_hash', 64);
            $t->unsignedSmallInteger('status');
            $t->json('body');
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['api_key_id', 'key']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('url', 500);
            $t->string('description', 120)->nullable();
            $t->string('secret', 80);
            $t->json('events');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->id();
            $t->string('uuid', 40)->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            $t->string('event', 60);
            $t->json('payload');
            $t->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedSmallInteger('response_code')->nullable();
            $t->unsignedInteger('latency_ms')->nullable();
            $t->string('last_error')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['webhook_deliveries', 'webhook_endpoints', 'idempotency_keys', 'api_requests', 'api_keys'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn('api_enabled'));
    }
};
