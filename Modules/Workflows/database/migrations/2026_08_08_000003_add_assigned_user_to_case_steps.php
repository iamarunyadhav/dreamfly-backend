<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `owner_role` is a free-text label ("Supervisor", "Application Unit Staff")
     * used for display only - nothing resolved it to an actual person. This adds
     * the first real per-step "who owns this right now" column, set when a step
     * completion hands off to a specific chosen staff member.
     */
    public function up(): void
    {
        Schema::table('case_steps', function (Blueprint $table) {
            $table->foreignId('assigned_user_id')->nullable()->after('owner_role')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('case_steps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_user_id');
        });
    }
};
