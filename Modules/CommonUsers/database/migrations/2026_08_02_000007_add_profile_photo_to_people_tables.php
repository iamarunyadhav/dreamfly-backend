<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_users', function (Blueprint $table) {
            $table->foreignId('profile_photo_file_id')
                ->nullable()
                ->after('paid_amount')
                ->constrained('files')
                ->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('profile_photo_file_id')
                ->nullable()
                ->after('paid_amount')
                ->constrained('files')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_photo_file_id');
        });

        Schema::table('common_users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_photo_file_id');
        });
    }
};
