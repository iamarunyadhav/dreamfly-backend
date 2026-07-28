<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onboarding-flow additions: a service category on leads/clients, and lead/client
 * document linkage + verification on files (so conversion can be gated on
 * verified documents and files can be re-filed into the client's folder tree).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('common_users', function (Blueprint $table) {
            $table->string('service_category')->default('visit_visa')->after('visa_type');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('service_category')->default('visit_visa')->after('visa_type');
        });

        // Make folder_id nullable (a lead document can sit un-filed until conversion).
        // Done explicitly: drop the FK, relax the column, re-add it as nullOnDelete
        // so deleting a folder no longer cascade-deletes the documents inside it.
        Schema::table('files', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
        });
        Schema::table('files', function (Blueprint $table) {
            $table->unsignedBigInteger('folder_id')->nullable()->change();
            $table->foreign('folder_id')->references('id')->on('folders')->nullOnDelete();

            $table->foreignId('common_user_id')->nullable()->after('folder_id')->constrained('common_users')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('common_user_id')->constrained('clients')->nullOnDelete();
            $table->boolean('verified')->default(false)->after('client_id');
            $table->timestamp('verified_at')->nullable()->after('verified');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('common_users', fn (Blueprint $table) => $table->dropColumn('service_category'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('service_category'));

        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('common_user_id');
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified', 'verified_at']);
        });
    }
};
