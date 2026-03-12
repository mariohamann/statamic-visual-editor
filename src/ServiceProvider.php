<?php

namespace Mariohamann\StatamicVisualEditor;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
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
