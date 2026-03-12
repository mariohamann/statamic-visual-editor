<?php

namespace Mariohamann\StatamicVisualEditor;

use Mariohamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
  protected $fieldtypes = [
    AutoUuidFieldtype::class,
  ];

  protected $vite = [
    'input' => [
      'resources/js/cp.js',
    ],
    'publicDirectory' => 'resources/dist',
  ];

  public function bootAddon()
  {
    //
  }
}
