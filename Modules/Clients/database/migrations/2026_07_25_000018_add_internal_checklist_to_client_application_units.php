<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_application_units', function (Blueprint $table) {
            // Office-side checklist, alongside the applicant and inviter ones.
            // The checklist *library* has supported an `internal` owner since the
            // library milestone; this is the matching per-case column.
            $table->json('internal_checklist')->nullable()->after('inviter_checklist');
        });
    }

    public function down(): void
    {
        Schema::table('client_application_units', function (Blueprint $table) {
            $table->dropColumn('internal_checklist');
        });
    }
};
