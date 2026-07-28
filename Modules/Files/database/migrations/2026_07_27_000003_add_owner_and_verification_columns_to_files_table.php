<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Codifies columns that were previously added to the live `files` table by
 * hand (common_user_id/client_id ownership, verification fields) without a
 * matching migration, so `migrate:fresh` reproduces the current schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (! Schema::hasColumn('files', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('folder_id')->constrained('clients')->nullOnDelete();
            }
            if (! Schema::hasColumn('files', 'common_user_id')) {
                $table->foreignId('common_user_id')->nullable()->after('client_id')->constrained('common_users')->nullOnDelete();
            }
            if (! Schema::hasColumn('files', 'verified')) {
                $table->boolean('verified')->default(false)->after('mime_type');
            }
            if (! Schema::hasColumn('files', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified');
            }
            if (! Schema::hasColumn('files', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            if (Schema::hasColumn('files', 'verified_by')) {
                $table->dropConstrainedForeignId('verified_by');
            }
            if (Schema::hasColumn('files', 'verified_at')) {
                $table->dropColumn('verified_at');
            }
            if (Schema::hasColumn('files', 'verified')) {
                $table->dropColumn('verified');
            }
            if (Schema::hasColumn('files', 'common_user_id')) {
                $table->dropConstrainedForeignId('common_user_id');
            }
            if (Schema::hasColumn('files', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
        });
    }
};
