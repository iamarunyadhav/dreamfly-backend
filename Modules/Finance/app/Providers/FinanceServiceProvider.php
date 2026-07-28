<?php

namespace Modules\Finance\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Finance\Repositories\Contracts\LedgerEntryRepositoryInterface;
use Modules\Finance\Repositories\LedgerEntryRepository;

class FinanceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Finance';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'finance';

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

        $this->app->bind(LedgerEntryRepositoryInterface::class, LedgerEntryRepository::class);
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
