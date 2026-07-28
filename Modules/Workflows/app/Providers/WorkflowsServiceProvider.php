<?php

namespace Modules\Workflows\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Workflows\Repositories\Contracts\WorkflowTemplateRepositoryInterface;
use Modules\Workflows\Repositories\WorkflowTemplateRepository;

class WorkflowsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Workflows';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'workflows';

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

        $this->app->bind(WorkflowTemplateRepositoryInterface::class, WorkflowTemplateRepository::class);
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
