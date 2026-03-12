<?php

namespace Mariohamann\StatamicVisualEditor\Tests;

use Mariohamann\StatamicVisualEditor\ServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_addon_service_provider_is_registered(): void
    {
        $this->assertArrayHasKey(ServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_service_provider_boots_without_error(): void
    {
        $this->assertTrue(true);
    }
}
