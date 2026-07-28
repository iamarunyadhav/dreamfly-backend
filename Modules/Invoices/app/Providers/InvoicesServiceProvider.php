<?php

namespace Modules\Invoices\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Invoices\Repositories\Contracts\InvoiceRepositoryInterface;
use Modules\Invoices\Repositories\InvoiceRepository;

class InvoicesServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Invoices';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'invoices';

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

        $this->app->bind(InvoiceRepositoryInterface::class, InvoiceRepository::class);
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
