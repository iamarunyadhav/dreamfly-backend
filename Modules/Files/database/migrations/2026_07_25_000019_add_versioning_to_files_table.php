<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // A corrected re-upload supersedes the old file instead of sitting
            // beside it. Every version in a chain shares the same root
            // (`version_root_id`); only the newest carries is_current = true.
            $table->unsignedInteger('version')->default(1)->after('size');
            $table->foreignId('version_root_id')->nullable()->after('version')
                ->constrained('files')->nullOnDelete();
            $table->foreignId('replaces_file_id')->nullable()->after('version_root_id')
                ->constrained('files')->nullOnDelete();
            $table->boolean('is_current')->default(true)->after('replaces_file_id');
            $table->text('version_note')->nullable()->after('is_current');
            $table->timestamp('superseded_at')->nullable()->after('version_note');

            $table->index(['version_root_id', 'version']);
            $table->index(['is_current']);
        });

        // Every pre-existing file becomes its own version-1 root, so the
        // "current version" query works uniformly without special-casing nulls.
        DB::table('files')->whereNull('version_root_id')->update([
            'version_root_id' => DB::raw('id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('version_root_id');
            $table->dropConstrainedForeignKey('replaces_file_id');
            $table->dropColumn(['version', 'is_current', 'version_note', 'superseded_at']);
        });
    }
};
