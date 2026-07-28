<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `visa_outcome` and `outcome_recorded_at` already live on clients; this adds
     * the supporting detail the decision stage needs (grant/refusal letter,
     * refusal reason, and the appeal path) alongside them.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('outcome_recorded_by')->nullable()->after('outcome_recorded_at')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('decision_file_id')->nullable()->after('outcome_recorded_by')
                ->constrained('files')->nullOnDelete();
            $table->text('refusal_reason')->nullable()->after('decision_file_id');
            // none | considering | lodged | won | lost | withdrawn
            $table->string('appeal_status')->default('none')->after('refusal_reason');
            $table->date('appeal_due_at')->nullable()->after('appeal_status');
            $table->text('appeal_notes')->nullable()->after('appeal_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('outcome_recorded_by');
            $table->dropConstrainedForeignId('decision_file_id');
            $table->dropColumn(['refusal_reason', 'appeal_status', 'appeal_due_at', 'appeal_notes']);
        });
    }
};
