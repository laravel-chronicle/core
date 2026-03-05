<?php

use Chronicle\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extends(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in(__DIR__);
