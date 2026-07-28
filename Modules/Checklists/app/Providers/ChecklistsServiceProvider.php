<?php

namespace Modules\Checklists\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Checklists\Repositories\Contracts\ChecklistTemplateRepositoryInterface;
use Modules\Checklists\Repositories\ChecklistTemplateRepository;

class ChecklistsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Checklists';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'checklists';

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

        $this->app->bind(ChecklistTemplateRepositoryInterface::class, ChecklistTemplateRepository::class);
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
