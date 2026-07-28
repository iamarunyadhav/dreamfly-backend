<?php

namespace Modules\Ocr\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class OcrServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Ocr';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ocr';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
