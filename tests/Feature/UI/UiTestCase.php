<?php

namespace Chronicle\Tests\Feature\UI;

use Chronicle\Tests\TestCase;

abstract class UiTestCase extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('chronicle.ui.enabled', true);
    }
}
