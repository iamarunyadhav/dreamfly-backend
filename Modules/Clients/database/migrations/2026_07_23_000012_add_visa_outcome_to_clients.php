<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // approved | refused | withdrawn | pending (null = not yet decided)
            $table->string('visa_outcome')->nullable()->after('current_stage');
            $table->timestamp('outcome_recorded_at')->nullable()->after('visa_outcome');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['visa_outcome', 'outcome_recorded_at']);
        });
    }
};
