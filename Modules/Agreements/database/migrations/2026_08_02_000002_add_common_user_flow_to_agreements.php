<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->foreignId('common_user_id')->nullable()->after('client_id')->constrained('common_users')->nullOnDelete();
            $table->foreignId('signed_file_id')->nullable()->after('generated_file_id')->constrained('files')->nullOnDelete();
            $table->timestamp('signed_at')->nullable()->after('signed_file_id');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('common_user_id');
            $table->dropConstrainedForeignId('signed_file_id');
            $table->dropColumn('signed_at');
        });
    }
};
