<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->foreignId('notification_template_id')->nullable()->after('requires_checklist')->constrained('message_templates')->nullOnDelete();
            // Free-form rule, e.g. "escalate_to:Supervisor after:2d".
            $table->string('escalation_rule')->nullable()->after('notification_template_id');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('notification_template_id');
            $table->dropColumn('escalation_rule');
        });
    }
};
