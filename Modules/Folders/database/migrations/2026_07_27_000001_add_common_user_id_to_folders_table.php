<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            if (! Schema::hasColumn('folders', 'common_user_id')) {
                $table->foreignId('common_user_id')->nullable()->after('client_id')->constrained('common_users')->nullOnDelete();
                $table->index(['common_user_id', 'parent_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex(['common_user_id', 'parent_id']);
            $table->dropConstrainedForeignId('common_user_id');
        });
    }
};
