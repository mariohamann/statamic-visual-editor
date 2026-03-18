<?php

namespace Mariohamann\StatamicVisualEditor\Fieldtypes;

use Illuminate\Support\Str;
use Statamic\Fields\Fieldtype;

class AutoUuidFieldtype extends Fieldtype
{
  protected static $handle = 'auto_uuid';

  protected $selectable = false;

  public function preProcess(mixed $data): string
  {
    return $data ?? (string) Str::uuid();
  }
}
