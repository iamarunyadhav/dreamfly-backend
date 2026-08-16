<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only rename: the "Documentation Unit" step/folder is now displayed
     * as "Correction Unit". The step `key` (documentation_unit) and the
     * "Documentation Unit Staff" role are unchanged - only the label seen by
     * users on already-created case steps and folders is backfilled here;
     * new clients pick up the new label straight from CaseStepService/FolderService.
     */
    public function up(): void
    {
        DB::table('case_steps')
            ->where('key', 'documentation_unit')
            ->where('name', 'Documentation Unit')
            ->update(['name' => 'Correction Unit']);

        DB::table('folders')
            ->where('name', 'Documentation Unit')
            ->update(['name' => 'Correction Unit']);
    }

    public function down(): void
    {
        DB::table('case_steps')
            ->where('key', 'documentation_unit')
            ->where('name', 'Correction Unit')
            ->update(['name' => 'Documentation Unit']);

        DB::table('folders')
            ->where('name', 'Correction Unit')
            ->update(['name' => 'Documentation Unit']);
    }
};
