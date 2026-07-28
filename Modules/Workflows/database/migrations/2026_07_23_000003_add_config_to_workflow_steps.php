<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // Machine key used to drive the runtime (e.g. documentation_unit).
            $table->string('key')->nullable()->after('name');
            // Expected SLA duration in days, used to compute a case step's due date.
            $table->unsignedInteger('duration_days')->nullable()->after('owner_role');
            // When true, the step cannot be completed until required checklist
            // items for the case are completed/verified.
            $table->boolean('requires_checklist')->default(false)->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn(['key', 'duration_days', 'requires_checklist']);
        });
    }
};
