<?php

namespace Mariohamann\StatamicVisualEditor\Tests;

use Illuminate\Support\Facades\Event;
use Mariohamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use Mariohamann\StatamicVisualEditor\Http\Middleware\InjectBridgeScript;
use Mariohamann\StatamicVisualEditor\ServiceProvider;
use Mariohamann\StatamicVisualEditor\Tags\VisualEdit;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Events\GlobalVariablesSaving;
use Statamic\Fields\FieldtypeRepository;

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

  public function test_fieldtype_registered(): void
  {
    $fieldtype = app(FieldtypeRepository::class)->find('auto_uuid');

    $this->assertInstanceOf(AutoUuidFieldtype::class, $fieldtype);
  }

  public function test_tag_registered(): void
  {
    $tag = app(VisualEdit::class);

    $this->assertInstanceOf(VisualEdit::class, $tag);
  }

  public function test_blade_helper_registered(): void
  {
    $this->assertTrue(function_exists('visual_edit'));
  }

  public function test_event_listener_registered_for_entry_blueprint_found(): void
  {
    $listeners = Event::getListeners(EntryBlueprintFound::class);

    $this->assertNotEmpty($listeners);
  }

  public function test_event_listener_registered_for_global_variables_blueprint_found(): void
  {
    $listeners = Event::getListeners(GlobalVariablesBlueprintFound::class);

    $this->assertNotEmpty($listeners);
  }

  public function test_event_listener_registered_for_entry_saving(): void
  {
    $listeners = Event::getListeners(EntrySaving::class);

    $this->assertNotEmpty($listeners);
  }

  public function test_event_listener_registered_for_global_variables_saving(): void
  {
    $listeners = Event::getListeners(GlobalVariablesSaving::class);

    $this->assertNotEmpty($listeners);
  }

  public function test_middleware_is_registered_in_web_group(): void
  {
    $middleware = $this->app['router']->getMiddlewareGroups()['web'] ?? [];

    $this->assertContains(InjectBridgeScript::class, $middleware);
  }
}
