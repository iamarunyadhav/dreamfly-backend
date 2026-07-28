<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('parent_id')->constrained('clients')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->after('client_id')->constrained('folders')->nullOnDelete();
            $table->string('scope')->default('global')->after('template_id');
            $table->boolean('auto_create_for_clients')->default(false)->after('is_general');
            $table->json('applies_to')->nullable()->after('auto_create_for_clients');

            $table->index(['client_id', 'parent_id']);
            $table->index(['template_id']);
            $table->index(['scope', 'is_general']);
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'parent_id']);
            $table->dropIndex(['template_id']);
            $table->dropIndex(['scope', 'is_general']);
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn(['scope', 'auto_create_for_clients', 'applies_to']);
        });
    }
};
