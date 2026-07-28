<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_admin_summaries', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('client_share_notes');
            $table->foreignId('generated_file_id')->nullable()->after('completed_by')->constrained('files')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_admin_summaries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('generated_file_id');
            $table->dropColumn('form_data');
        });
    }
};

