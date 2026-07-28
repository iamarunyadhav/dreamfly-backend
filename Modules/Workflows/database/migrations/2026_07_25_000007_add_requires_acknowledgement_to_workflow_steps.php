<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            // When true, the step cannot be completed until the client's
            // Responsibility Notice has been acknowledged (mirrors requires_checklist).
            $table->boolean('requires_acknowledgement')->default(false)->after('requires_checklist');
        });

        Schema::table('case_steps', function (Blueprint $table) {
            $table->boolean('requires_acknowledgement')->default(false)->after('requires_checklist');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropColumn('requires_acknowledgement');
        });

        Schema::table('case_steps', function (Blueprint $table) {
            $table->dropColumn('requires_acknowledgement');
        });
    }
};
