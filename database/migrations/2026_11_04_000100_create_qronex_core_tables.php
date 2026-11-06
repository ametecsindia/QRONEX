<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ponytail: one migration for the whole Stage 2 schema (fresh project). Split per table once it ships.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug', 60)->unique();
            $t->string('org_type')->nullable();
            $t->string('industry')->nullable();
            $t->string('country')->default('India');
            $t->string('state')->nullable();
            $t->string('city')->nullable();
            $t->string('address')->nullable();
            $t->string('gstin', 15)->nullable();
            $t->string('website')->nullable();
            $t->string('logo_path')->nullable();
            $t->enum('status', ['trial', 'active', 'grace', 'suspended', 'cancelled'])->default('trial');
            $t->enum('customer_type', ['independent', 'smartprs', 'smartept', 'smartdcm'])->default('independent');
            $t->text('commercial_note')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete(); // null = platform staff
            $t->enum('role', ['super_admin', 'support_admin', 'company_owner', 'company_admin', 'office_manager', 'attendance_approver'])->default('attendance_approver')->after('password'); // default: SQLite (tests) can't add a NOT NULL column without one
            $t->boolean('is_active')->default(true)->after('role');
            $t->timestamp('last_login_at')->nullable()->after('is_active');
        });

        Schema::create('offices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->string('code', 20);
            $t->string('slug', 60);
            $t->string('name');
            $t->string('address')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->unsignedInteger('geofence_radius_m')->default(150);
            $t->string('timezone', 40)->default('Asia/Kolkata');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
            $t->unique(['tenant_id', 'slug']);
        });

        Schema::create('office_user', function (Blueprint $t) {
            $t->foreignId('office_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['office_id', 'user_id']);
        });

        Schema::create('gates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('office_id')->constrained()->cascadeOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['office_id', 'code']);
        });

        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('office_id')->nullable()->constrained()->nullOnDelete();
            $t->string('employee_code', 40);
            $t->string('hrms_ref')->nullable();
            $t->string('name');
            $t->string('email')->nullable();
            $t->string('mobile', 20)->nullable();
            $t->string('department')->nullable();
            $t->string('designation')->nullable();
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->unique(['tenant_id', 'employee_code']);
        });

        Schema::create('devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $t->enum('platform', ['android', 'ios']);
            $t->string('model')->nullable();
            $t->string('os_version')->nullable();
            $t->string('install_ref', 80)->unique();
            $t->text('public_key')->nullable();
            $t->enum('status', ['pending', 'approved', 'rejected', 'revoked'])->default('pending');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
            $t->index(['tenant_id', 'status']);
        });

        Schema::create('plans', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name');
            $t->string('description')->nullable();
            $t->unsignedInteger('min_employees')->default(1);
            $t->unsignedInteger('max_employees')->default(100000);
            $t->boolean('is_active')->default(true);
            $t->boolean('is_popular')->default(false);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // Immutable: editing a plan inserts a new version; subscriptions lock the version they bought.
        Schema::create('plan_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->enum('pricing_model', ['per_employee', 'slab', 'custom']);
            $t->decimal('per_employee_price', 10, 2)->default(0);
            $t->decimal('slab_price', 10, 2)->default(0);
            $t->decimal('min_billing', 10, 2)->default(0);
            $t->decimal('quarterly_discount_pct', 5, 2)->default(0);
            $t->decimal('annual_discount_pct', 5, 2)->default(0);
            $t->decimal('onboarding_fixed', 10, 2)->default(0);
            $t->decimal('onboarding_per_employee', 10, 2)->default(0);
            $t->unsignedInteger('trial_days')->default(14);
            $t->boolean('api_included')->default(false);
            $t->unsignedInteger('sms_allowance')->default(0);
            $t->string('note')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['plan_id', 'version']);
        });

        Schema::create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('plan_version_id')->constrained()->restrictOnDelete();
            $t->enum('billing_cycle', ['monthly', 'quarterly', 'annual'])->default('monthly');
            $t->unsignedInteger('employee_count');
            $t->enum('status', ['trial', 'active', 'grace', 'suspended', 'cancelled'])->default('trial');
            $t->timestamp('trial_ends_at')->nullable();
            $t->date('current_period_start')->nullable();
            $t->date('current_period_end')->nullable();
            $t->enum('arrangement', ['published', 'discount', 'complimentary', 'custom'])->default('published');
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->decimal('custom_monthly', 10, 2)->nullable();
            $t->timestamps();
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $t->string('number', 30)->unique();
            $t->date('issued_on');
            $t->string('description');
            $t->decimal('subtotal', 12, 2);
            $t->decimal('onboarding', 12, 2)->default(0);
            $t->decimal('taxable', 12, 2);
            $t->decimal('gst_rate', 5, 2)->default(18);
            $t->decimal('cgst', 12, 2)->default(0);
            $t->decimal('sgst', 12, 2)->default(0);
            $t->decimal('igst', 12, 2)->default(0);
            $t->decimal('total', 12, 2);
            $t->enum('status', ['draft', 'issued', 'paid', 'failed', 'void'])->default('issued');
            $t->string('payment_ref')->nullable();
            $t->timestamps();
        });

        Schema::create('tenant_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('qr_interval_s')->default(3);
            $t->unsignedTinyInteger('grace_s')->default(5);
            $t->unsignedTinyInteger('device_limit')->default(1);
            $t->boolean('require_device_approval')->default(true);
            $t->unsignedSmallInteger('duplicate_window_min')->default(2);
            $t->unsignedSmallInteger('gps_accuracy_m')->default(50);
            $t->unsignedSmallInteger('retention_months')->default(24);
            $t->text('location_notice')->nullable();
            $t->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $t) {
            $t->string('key', 60)->primary();
            $t->json('value');
            $t->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80);
            $t->string('subject')->nullable();
            $t->json('meta')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'platform_settings', 'tenant_settings', 'invoices', 'subscriptions', 'plan_versions', 'plans', 'devices', 'employees', 'gates', 'office_user', 'offices'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tenant_id');
            $t->dropColumn(['role', 'is_active', 'last_login_at']);
        });
        Schema::dropIfExists('tenants');
    }
};
