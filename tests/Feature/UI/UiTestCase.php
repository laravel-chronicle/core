<?php

namespace Chronicle\Tests\Feature\UI;

trait UiTestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('chronicle.ui.enabled', true);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/login', fn () => 'login')->name('login');
    }
}
