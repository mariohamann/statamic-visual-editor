<?php

namespace Mariohamann\StatamicVisualEditor\Tests;

use Mariohamann\StatamicVisualEditor\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
