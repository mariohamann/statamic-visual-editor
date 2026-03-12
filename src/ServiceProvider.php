<?php

namespace Mariohamann\StatamicVisualEditor;

use Mariohamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use Mariohamann\StatamicVisualEditor\Listeners\InjectVisualIdIntoBlueprint;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
  protected $fieldtypes = [
    AutoUuidFieldtype::class,
  ];

  protected $listen = [
    EntryBlueprintFound::class => [
      InjectVisualIdIntoBlueprint::class,
    ],
    GlobalVariablesBlueprintFound::class => [
      InjectVisualIdIntoBlueprint::class,
    ],
  ];

  protected $vite = [
    'input' => [
      'resources/js/cp.js',
    ],
    'publicDirectory' => 'resources/dist',
  ];

  public function bootAddon(): void
  {
    //
  }
}
