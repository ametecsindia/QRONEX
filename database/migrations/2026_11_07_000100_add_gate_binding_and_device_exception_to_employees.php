<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone approval binds the employee to an office + gate, with a scan scope:
 *   gate   = only the bound gate · office = any gate of the bound office (old behaviour) · any = any office of the company.
 * max_devices = admin-granted exception to the one-phone rule (null = 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->foreignId('gate_id')->nullable()->after('office_id')->constrained()->nullOnDelete();
            $t->string('scan_scope', 10)->default('office')->after('gate_id');
            $t->unsignedTinyInteger('max_devices')->nullable()->after('scan_scope');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $t) {
            $t->dropConstrainedForeignId('gate_id');
            $t->dropColumn(['scan_scope', 'max_devices']);
        });
    }
};
