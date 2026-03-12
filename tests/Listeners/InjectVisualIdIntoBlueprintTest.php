<?php

namespace Mariohamann\StatamicVisualEditor\Tests\Listeners;

use Mariohamann\StatamicVisualEditor\Tests\TestCase;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Fields\Blueprint;

class InjectVisualIdIntoBlueprintTest extends TestCase
{
    private function makeBlueprint(array $fields): Blueprint
    {
        $blueprint = new Blueprint;
        $blueprint->setContents([
            'tabs' => [
                'main' => [
                    'sections' => [
                        ['fields' => $fields],
                    ],
                ],
            ],
        ]);

        return $blueprint;
    }

    private function replicatorWithGroupedSets(array $sets): array
    {
        return [
            'handle' => 'content',
            'field' => [
                'type' => 'replicator',
                'sets' => [
                    'main' => [
                        'display' => 'Main',
                        'sets' => $sets,
                    ],
                ],
            ],
        ];
    }

    private function bardWithGroupedSets(array $sets): array
    {
        return [
            'handle' => 'body',
            'field' => [
                'type' => 'bard',
                'sets' => [
                    'main' => [
                        'display' => 'Main',
                        'sets' => $sets,
                    ],
                ],
            ],
        ];
    }

    private function textSet(string $handle): array
    {
        return [
            'display' => ucfirst($handle),
            'fields' => [
                ['handle' => 'text', 'field' => ['type' => 'textarea']],
            ],
        ];
    }

    private function getSetsFields(Blueprint $blueprint, string $fieldHandle, string $group, string $setHandle): array
    {
        return $blueprint->contents()['tabs']['main']['sections'][0]['fields'][0]['field']['sets'][$group]['sets'][$setHandle]['fields'] ?? [];
    }

    // -------------------------------------------------------------------------
    // EntryBlueprintFound
    // -------------------------------------------------------------------------

    public function test_replicator_sets_gain_visual_id_on_entry_blueprint_found(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorWithGroupedSets([
                'text_block' => $this->textSet('text_block'),
                'image' => $this->textSet('image'),
            ]),
        ]);

        EntryBlueprintFound::dispatch($blueprint);

        $textBlockFields = $this->getSetsFields($blueprint, 'content', 'main', 'text_block');
        $imageFields = $this->getSetsFields($blueprint, 'content', 'main', 'image');

        $this->assertContains('_visual_id', array_column($textBlockFields, 'handle'));
        $this->assertContains('_visual_id', array_column($imageFields, 'handle'));
    }

    public function test_bard_sets_gain_visual_id_on_entry_blueprint_found(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->bardWithGroupedSets([
                'text_block' => $this->textSet('text_block'),
            ]),
        ]);

        EntryBlueprintFound::dispatch($blueprint);

        $fields = $this->getSetsFields($blueprint, 'body', 'main', 'text_block');

        $this->assertContains('_visual_id', array_column($fields, 'handle'));
    }

    public function test_nested_replicator_in_bard_gains_visual_id_at_every_level(): void
    {
        $nestedReplicator = [
            'handle' => 'nested',
            'field' => [
                'type' => 'replicator',
                'sets' => [
                    'inner' => [
                        'display' => 'Inner',
                        'sets' => [
                            'inner_set' => $this->textSet('inner_set'),
                        ],
                    ],
                ],
            ],
        ];

        $blueprint = $this->makeBlueprint([
            $this->bardWithGroupedSets([
                'outer_set' => [
                    'display' => 'Outer Set',
                    'fields' => [
                        ['handle' => 'label', 'field' => ['type' => 'text']],
                        $nestedReplicator,
                    ],
                ],
            ]),
        ]);

        EntryBlueprintFound::dispatch($blueprint);

        $contents = $blueprint->contents();
        $outerFields = $contents['tabs']['main']['sections'][0]['fields'][0]['field']['sets']['main']['sets']['outer_set']['fields'];
        $innerSets = $contents['tabs']['main']['sections'][0]['fields'][0]['field']['sets']['main']['sets']['outer_set']['fields'][1]['field']['sets']['inner']['sets'];

        // Outer set must have _visual_id
        $this->assertContains('_visual_id', array_column($outerFields, 'handle'));
        // Inner set must also have _visual_id
        $this->assertContains('_visual_id', array_column($innerSets['inner_set']['fields'], 'handle'));
    }

    public function test_blueprint_without_replicator_or_bard_is_unchanged(): void
    {
        $fields = [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'slug', 'field' => ['type' => 'slug']],
        ];

        $blueprint = $this->makeBlueprint($fields);

        EntryBlueprintFound::dispatch($blueprint);

        $resultFields = $blueprint->contents()['tabs']['main']['sections'][0]['fields'];

        $this->assertCount(2, $resultFields);
        $this->assertNotContains('_visual_id', array_column($resultFields, 'handle'));
    }

    public function test_existing_visual_id_is_not_duplicated(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorWithGroupedSets([
                'text_block' => [
                    'display' => 'Text Block',
                    'fields' => [
                        ['handle' => 'text', 'field' => ['type' => 'textarea']],
                        ['handle' => '_visual_id', 'field' => ['type' => 'auto_uuid']],
                    ],
                ],
            ]),
        ]);

        EntryBlueprintFound::dispatch($blueprint);
        // Fire twice to confirm idempotency
        EntryBlueprintFound::dispatch($blueprint);

        $fields = $this->getSetsFields($blueprint, 'content', 'main', 'text_block');
        $visualIdCount = count(array_filter(array_column($fields, 'handle'), fn ($h) => $h === '_visual_id'));

        $this->assertSame(1, $visualIdCount);
    }

    public function test_original_yaml_is_not_modified(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorWithGroupedSets([
                'text_block' => $this->textSet('text_block'),
            ]),
        ]);

        // Simulate loading the blueprint from a file path
        $yamlPath = base_path('resources/blueprints/globals/social_media.yaml');
        if (! file_exists($yamlPath)) {
            $this->markTestSkipped('social_media.yaml not available in test environment.');
        }

        $contentsBeforeEvent = file_get_contents($yamlPath);

        EntryBlueprintFound::dispatch($blueprint);

        $this->assertSame($contentsBeforeEvent, file_get_contents($yamlPath));
    }

    // -------------------------------------------------------------------------
    // GlobalVariablesBlueprintFound
    // -------------------------------------------------------------------------

    public function test_replicator_sets_gain_visual_id_on_global_variables_blueprint_found(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorWithGroupedSets([
                'text_block' => $this->textSet('text_block'),
            ]),
        ]);

        GlobalVariablesBlueprintFound::dispatch($blueprint);

        $fields = $this->getSetsFields($blueprint, 'content', 'main', 'text_block');

        $this->assertContains('_visual_id', array_column($fields, 'handle'));
    }

    public function test_auto_uuid_type_is_injected_in_visual_id_field(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorWithGroupedSets([
                'text_block' => $this->textSet('text_block'),
            ]),
        ]);

        EntryBlueprintFound::dispatch($blueprint);

        $fields = $this->getSetsFields($blueprint, 'content', 'main', 'text_block');
        $visualIdField = collect($fields)->firstWhere('handle', '_visual_id');

        $this->assertNotNull($visualIdField);
        $this->assertSame('auto_uuid', $visualIdField['field']['type']);
    }
}
