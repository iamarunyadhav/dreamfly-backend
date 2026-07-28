<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Modules that expose the standard view/create/edit/delete permission set.
     * Keep this list in sync with Modules/* as new domains come online.
     */
    private const CRUD_MODULES = [
        'users', 'roles', 'contacts', 'folders',
        'common-users', 'clients', 'payments', 'invoices',
        'checklists', 'workflows', 'communications', 'finance', 'forms', 'services',
    ];

    private const CRUD_ACTIONS = ['view', 'create', 'edit', 'update', 'delete'];

    // Modules with a narrower permission set than the standard CRUD four.
    private const CUSTOM_MODULE_PERMISSIONS = [
        'clients' => ['view', 'create', 'edit', 'update', 'delete', 'convert', 'assign'],
        'agreements' => ['view', 'create', 'edit', 'update', 'delete', 'generate', 'share'],
        'folders' => ['view', 'create', 'edit', 'update', 'delete', 'upload', 'download', 'move', 'restore'],
        'files' => ['view', 'create', 'upload', 'download', 'verify', 'delete'],
        'invoices' => ['view', 'create', 'edit', 'update', 'delete', 'generate', 'share', 'record_payment'],
        'payments' => ['view', 'create', 'edit', 'update', 'delete', 'verify', 'refund', 'adjust'],
        'communications' => ['view', 'create', 'edit', 'update', 'delete', 'send', 'share', 'retry'],
        'application-unit' => ['view', 'create', 'edit', 'update', 'complete', 'generate'],
        'documentation-unit' => ['view', 'create', 'edit', 'update', 'delete', 'complete', 'assign'],
        'supervisor-review' => ['view', 'comment', 'approve', 'send_back'],
        'audit-logs' => ['view'],
        'reports' => ['view'],
        'system' => ['view', 'edit'],
        'ocr' => ['manage', 'run', 'view'],
    ];

    /** @var array<string, string[]> module => actions, built once in run() */
    private array $moduleActions = [];

    public function run(): void
    {
        foreach (self::CRUD_MODULES as $module) {
            $this->moduleActions[$module] = self::CRUD_ACTIONS;
        }

        foreach (self::CUSTOM_MODULE_PERMISSIONS as $module => $actions) {
            $this->moduleActions[$module] = $actions;
        }

        $allPermissionNames = collect($this->moduleActions)
            ->flatMap(fn (array $actions, string $module) => collect($actions)->map(fn (string $action) => "{$module}.{$action}"))
            ->values()
            ->all();

        collect($allPermissionNames)->each(
            fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])
        );

        $readOnly = collect($allPermissionNames)->filter(fn (string $name) => str_ends_with($name, '.view'))->all();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web'])->syncPermissions($allPermissionNames);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'])->syncPermissions($allPermissionNames);
        Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web'])->syncPermissions($allPermissionNames);

        Role::firstOrCreate(['name' => 'Supervisor', 'guard_name' => 'web'])->syncPermissions(
            collect($allPermissionNames)->reject(fn (string $name) => str_starts_with($name, 'system.'))->all()
        );

        // Unit staff read the supervisor's verdict and answer on the thread, but
        // sign-off itself (approve / send back) stays with the Supervisor tier.
        $reviewParticipant = ['supervisor-review.view', 'supervisor-review.comment'];

        Role::firstOrCreate(['name' => 'Application Unit Staff', 'guard_name' => 'web'])->syncPermissions(
            $this->modulePermissions(['clients', 'checklists', 'workflows', 'folders', 'files', 'contacts', 'communications', 'application-unit', 'ocr'], extra: $reviewParticipant)
        );

        Role::firstOrCreate(['name' => 'Documentation Unit Staff', 'guard_name' => 'web'])->syncPermissions(
            $this->modulePermissions(['clients', 'checklists', 'folders', 'files', 'contacts', 'documentation-unit', 'ocr'], extra: $reviewParticipant)
        );

        Role::firstOrCreate(['name' => 'Accounts Staff', 'guard_name' => 'web'])->syncPermissions(
            $this->modulePermissions(['payments', 'invoices', 'finance'], viewOnly: ['clients'])
        );

        Role::firstOrCreate(['name' => 'Reception Staff', 'guard_name' => 'web'])->syncPermissions(
            $this->modulePermissions(['common-users', 'contacts', 'agreements'])
        );

        Role::firstOrCreate(['name' => 'Read-only Staff', 'guard_name' => 'web'])->syncPermissions($readOnly);
    }

    /**
     * @param  string[]  $modules  full-access modules (uses each module's own action set)
     * @param  string[]  $viewOnly  additional modules granted view-only
     * @param  string[]  $extra  individual permission names to append verbatim
     */
    private function modulePermissions(array $modules, array $viewOnly = [], array $extra = []): array
    {
        $permissions = collect($extra);

        foreach ($modules as $module) {
            foreach ($this->moduleActions[$module] ?? self::CRUD_ACTIONS as $action) {
                $permissions->push("{$module}.{$action}");
            }
        }

        foreach ($viewOnly as $module) {
            $permissions->push("{$module}.view");
        }

        return $permissions->unique()->values()->all();
    }
}
