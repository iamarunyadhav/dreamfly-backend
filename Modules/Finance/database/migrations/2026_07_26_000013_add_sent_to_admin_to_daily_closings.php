<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->timestamp('sent_to_admin_at')->nullable()->after('generated_file_id');
            $table->foreignId('sent_to_admin_by')->nullable()->after('sent_to_admin_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_closings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sent_to_admin_by');
            $table->dropColumn('sent_to_admin_at');
        });
    }
};
