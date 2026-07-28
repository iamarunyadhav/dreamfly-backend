<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // When true, the step cannot be completed until the client's final
            // document handover has been recorded and archived (mirrors
            // requires_checklist / requires_acknowledgement).
            $table->boolean('requires_closure_record')->default(false)->after('requires_acknowledgement');
        });

        Schema::table('case_steps', function (Blueprint $table) {
            $table->boolean('requires_closure_record')->default(false)->after('requires_acknowledgement');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('requires_closure_record');
        });

        Schema::table('case_steps', function (Blueprint $table) {
            $table->dropColumn('requires_closure_record');
        });
    }
};
