<?php

namespace Mariohamann\StatamicVisualEditor;

use Mariohamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use Mariohamann\StatamicVisualEditor\Listeners\InjectVisualIdIntoBlueprint;
use Mariohamann\StatamicVisualEditor\Listeners\StampVisualIds;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Events\GlobalVariablesSaving;
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
    EntrySaving::class => [
      StampVisualIds::class,
    ],
    GlobalVariablesSaving::class => [
      StampVisualIds::class,
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
