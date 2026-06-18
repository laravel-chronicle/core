<?php

declare(strict_types=1);

namespace Chronicle\Tests\Feature\UI;

use Illuminate\Support\Facades\Gate;

trait UiTestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('chronicle.ui.enabled', true);

        // Grant view-chronicle to all authenticated users in UI tests.
        // Production apps must register this gate explicitly in their AuthServiceProvider.
        $this->afterApplicationCreated(function () {
            Gate::define('view-chronicle', fn () => true);
        });
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'login')->name('login');
    }
}
