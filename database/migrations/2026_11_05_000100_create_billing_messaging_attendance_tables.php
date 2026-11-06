<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stages 3–5: payments, messaging, QR displays, attendance, device tokens.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gates', function (Blueprint $t) {
            $t->string('display_secret', 64)->nullable();
            $t->timestamp('display_last_seen_at')->nullable();
            $t->string('display_agent')->nullable();
        });

        Schema::table('employees', function (Blueprint $t) {
            $t->string('activation_code_hash')->nullable();
            $t->timestamp('activation_expires_at')->nullable();
        });

        Schema::table('devices', function (Blueprint $t) {
            $t->string('token_hash', 64)->nullable()->unique();
        });

        Schema::create('attendance_events', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('type', ['IN', 'OUT']);
            $t->string('mode', 20)->default('OFFICE_QR');
            $t->unsignedBigInteger('qr_window')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedSmallInteger('accuracy_m')->nullable();
            $t->unsignedInteger('distance_m')->nullable();
            $t->timestamp('recorded_at');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'recorded_at']);
            $t->index(['employee_id', 'recorded_at']);
            $t->unique(['employee_id', 'gate_id', 'qr_window']); // replay guard at DB level
        });

        Schema::create('attendance_rejections', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->unique();
            $t->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();
            $t->string('reason_code', 40);
            $t->string('reason');
            $t->json('checks')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedSmallInteger('accuracy_m')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $t->string('order_id', 60)->unique();
            $t->string('payment_id', 60)->nullable()->unique();
            $t->unsignedBigInteger('amount_paise');
            $t->string('currency', 3)->default('INR');
            $t->enum('status', ['created', 'paid', 'failed'])->default('created');
            $t->json('meta');
            $t->string('failure_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $t) {
            $t->id();
            $t->string('event_id', 80)->unique(); // webhook idempotency
            $t->string('event', 60);
            $t->json('payload');
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('message_templates', function (Blueprint $t) {
            $t->id();
            $t->enum('channel', ['email', 'sms']);
            $t->string('key', 40);
            $t->string('name');
            $t->string('subject')->nullable();
            $t->text('body');
            $t->string('dlt_template_id', 40)->nullable();
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->unique(['channel', 'key']);
        });

        Schema::create('message_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('channel', ['email', 'sms']);
            $t->string('template_key', 40);
            $t->string('to');
            $t->string('subject')->nullable();
            $t->enum('status', ['sent', 'failed', 'skipped']);
            $t->string('error')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['message_logs', 'message_templates', 'payment_events', 'payments', 'attendance_rejections', 'attendance_events'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn('token_hash'));
        Schema::table('employees', fn (Blueprint $t) => $t->dropColumn(['activation_code_hash', 'activation_expires_at']));
        Schema::table('gates', fn (Blueprint $t) => $t->dropColumn(['display_secret', 'display_last_seen_at', 'display_agent']));
    }
};
