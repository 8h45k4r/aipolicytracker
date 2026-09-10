<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests render Inertia views; skip the Vite manifest lookup so
        // they run without a front-end build.
        $this->withoutVite();
    }
}
