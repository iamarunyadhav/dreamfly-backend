<?php

namespace Modules\Payments\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Payments\Repositories\Contracts\PaymentRepositoryInterface;
use Modules\Payments\Repositories\PaymentRepository;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Payments';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'payments';

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

        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
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
