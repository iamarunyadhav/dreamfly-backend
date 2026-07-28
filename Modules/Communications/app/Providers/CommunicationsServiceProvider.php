<?php

namespace Modules\Communications\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Communications\Repositories\Contracts\MessageTemplateRepositoryInterface;
use Modules\Communications\Repositories\MessageTemplateRepository;
use Modules\Communications\Repositories\Contracts\MessageRepositoryInterface;
use Modules\Communications\Repositories\MessageRepository;

class CommunicationsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Communications';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'communications';

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

        $this->app->bind(MessageTemplateRepositoryInterface::class, MessageTemplateRepository::class);
        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);
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
