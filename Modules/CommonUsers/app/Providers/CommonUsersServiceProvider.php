<?php

namespace Modules\CommonUsers\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\CommonUsers\Repositories\Contracts\CommonUserRepositoryInterface;
use Modules\CommonUsers\Repositories\CommonUserRepository;

class CommonUsersServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'CommonUsers';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'commonusers';

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

        $this->app->bind(CommonUserRepositoryInterface::class, CommonUserRepository::class);
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
