<?php

namespace Modules\Forms\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Forms\Repositories\Contracts\FormRepositoryInterface;
use Modules\Forms\Repositories\FormRepository;

class FormsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Forms';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'forms';

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

        $this->app->bind(FormRepositoryInterface::class, FormRepository::class);
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
