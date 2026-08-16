<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Data-only rename, mirroring the 2026-08-08 stage-label rename: the
     * "Documentation Unit Staff" role has actually owned the "Correction
     * Unit" stage since that rename, so its name is now taken by a brand-new
     * role for the genuinely new Documentation Unit stage. Renamed in place
     * (not created fresh) so every existing user keeps the role via the
     * unchanged role_id in model_has_roles.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('name', 'Documentation Unit Staff')
            ->where('guard_name', 'web')
            ->update(['name' => 'Correction Unit Staff']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('name', 'Correction Unit Staff')
            ->where('guard_name', 'web')
            ->update(['name' => 'Documentation Unit Staff']);
    }
};
