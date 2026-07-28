<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            // Which library this item belongs to.
            $table->string('owner')->default('applicant')->after('title'); // applicant | inviter | internal
            $table->string('status')->default('published')->after('document_required'); // draft | published
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->boolean('is_active')->default(true)->after('version');

            $table->index(['owner', 'status']);
        });

        // Backfill owner from the existing category so current items land in the
        // right library instead of all defaulting to applicant.
        DB::table('checklist_templates')->where('category', 'inviter')->update(['owner' => 'inviter']);
        DB::table('checklist_templates')->whereNotIn('category', ['applicant', 'inviter'])->update(['owner' => 'internal']);
    }

    public function down(): void
    {
        Schema::table('checklist_templates', function (Blueprint $table) {
            $table->dropColumn(['owner', 'status', 'version', 'is_active']);
        });
    }
};
