<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Behave like Redis: values go through serialize(), and objects are not allowed back out.
        config(['cache.stores.array.serialize' => true]);
    }
}
