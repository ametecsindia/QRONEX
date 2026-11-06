<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Remaining prototype features: remote attendance, punch corrections, device replacement, support tickets,
// notification preferences, sign-up email verification.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('remote_permissions', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['wfh', 'field_duty', 'client_site', 'temporary_remote']);
            $t->date('from_date');
            $t->date('to_date');
            $t->string('location_label')->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('radius_m')->nullable();
            $t->boolean('location_waived')->default(false); // audited exception: no location check
            $t->string('reason', 255);
            $t->enum('status', ['pending', 'approved', 'rejected', 'revoked'])->default('pending');
            $t->enum('requested_via', ['portal', 'app', 'api'])->default('portal');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
            $t->index(['employee_id', 'from_date', 'to_date']);
        });

        Schema::create('punch_corrections', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 20)->unique();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['IN', 'OUT']);
            $t->dateTime('punch_at'); // UTC; requested as an IST date + time
            $t->string('reason', 255);
            $t->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $t->enum('requested_via', ['portal', 'app'])->default('app');
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::table('attendance_events', function (Blueprint $t) {
            $t->foreignId('remote_permission_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('punch_correction_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('adjusted')->default(false); // MANUAL_EXCEPTION from an approved correction — never edits an existing event
        });

        Schema::table('devices', function (Blueprint $t) {
            $t->foreignId('replaces_device_id')->nullable()->constrained('devices')->nullOnDelete();
        });

        Schema::table('tenant_settings', function (Blueprint $t) {
            $t->boolean('notify_device_requests')->default(true);
            $t->boolean('notify_remote_requests')->default(true);
            $t->string('daily_report_email')->nullable(); // yesterday's register as CSV at 07:00 IST
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('email_code_hash')->nullable(); // sign-up email verification (email_verified_at already exists)
            $t->timestamp('email_code_expires_at')->nullable();
        });

        Schema::create('support_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('ref', 12)->unique();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); // null = public enquiry
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 20)->nullable();
            $t->string('subject');
            $t->enum('area', ['display', 'app', 'billing', 'api', 'sales', 'other'])->default('other');
            $t->enum('priority', ['normal', 'high', 'urgent'])->default('normal');
            $t->enum('status', ['open', 'answered', 'closed'])->default('open');
            $t->timestamps();
            $t->index(['status', 'updated_at']);
        });

        Schema::create('support_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('from_staff')->default(false);
            $t->text('body');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['email_code_hash', 'email_code_expires_at']));
        Schema::table('tenant_settings', fn (Blueprint $t) => $t->dropColumn(['notify_device_requests', 'notify_remote_requests', 'daily_report_email']));
        Schema::table('devices', fn (Blueprint $t) => $t->dropConstrainedForeignId('replaces_device_id'));
        Schema::table('attendance_events', function (Blueprint $t) {
            $t->dropConstrainedForeignId('remote_permission_id');
            $t->dropConstrainedForeignId('punch_correction_id');
            $t->dropColumn('adjusted');
        });
        Schema::dropIfExists('punch_corrections');
        Schema::dropIfExists('remote_permissions');
    }
};
