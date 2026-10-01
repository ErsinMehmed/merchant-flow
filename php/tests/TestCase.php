<?php

namespace Tests;

use App\Ship\Parents\Tests\TestCase as ParentTestCase;
use Laravel\Nova\Nova;

abstract class TestCase extends ParentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nova builds the page's script variables (including the signed-in
        // user's menu) once per process and keeps them in a static property.
        // PHP-FPM starts every request clean; a test process does not.
        Nova::$jsonVariables = [];
    }
}
