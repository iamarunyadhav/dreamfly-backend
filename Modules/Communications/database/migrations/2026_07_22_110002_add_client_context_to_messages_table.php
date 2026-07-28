<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('message_template_id')->constrained()->nullOnDelete();
            $table->string('workflow_step')->nullable()->after('client_id');

            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('workflow_step');
        });
    }
};
