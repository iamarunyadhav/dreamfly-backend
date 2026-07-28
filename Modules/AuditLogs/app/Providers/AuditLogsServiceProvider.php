<?php

namespace Modules\AuditLogs\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\AuditLogs\Repositories\Contracts\AuditLogRepositoryInterface;
use Modules\AuditLogs\Repositories\AuditLogRepository;

class AuditLogsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'AuditLogs';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'auditlogs';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
    }

    /**
     * Define module schedules.
     * 
     * @param $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
