<?php

namespace MarioHamann\StatamicVisualEditor;

use MarioHamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use MarioHamann\StatamicVisualEditor\Http\Middleware\InjectBridgeScript;
use MarioHamann\StatamicVisualEditor\Listeners\InjectVisualIdIntoBlueprint;
use MarioHamann\StatamicVisualEditor\Listeners\StripVisualIds;
use MarioHamann\StatamicVisualEditor\Tags\VisualEdit;
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

    protected $tags = [
        VisualEdit::class,
    ];

    protected $listen = [
        EntryBlueprintFound::class => [
            InjectVisualIdIntoBlueprint::class,
        ],
        GlobalVariablesBlueprintFound::class => [
            InjectVisualIdIntoBlueprint::class,
        ],
        EntrySaving::class => [
            StripVisualIds::class,
        ],
        GlobalVariablesSaving::class => [
            StripVisualIds::class,
        ],
    ];

    protected $middlewareGroups = [
        'web' => [
            InjectBridgeScript::class,
        ],
    ];

    protected $vite = [
        'input' => [
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];
}
