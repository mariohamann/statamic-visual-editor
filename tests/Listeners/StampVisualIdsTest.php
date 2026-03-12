<?php

namespace Mariohamann\StatamicVisualEditor\Tests\Listeners;

use Mariohamann\StatamicVisualEditor\Tests\TestCase;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;
use Statamic\Fields\Blueprint;

class StampVisualIdsTest extends TestCase
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

    private function makeTestObject(Blueprint $blueprint, array $data): object
    {
        return new class($blueprint, $data)
        {
            private array $currentData;

            public function __construct(
                private Blueprint $bp,
                array $initial
            ) {
                $this->currentData = $initial;
            }

            public function blueprint(): Blueprint
            {
                return $this->bp;
            }

            public function data(mixed $value = null): mixed
            {
                if ($value === null) {
                    return collect($this->currentData);
                }
                $this->currentData = $value;

                return $this;
            }
        };
    }

    private function replicatorField(string $handle, array $sets): array
    {
        return [
            'handle' => $handle,
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

    private function bardField(string $handle, array $sets): array
    {
        return [
            'handle' => $handle,
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

    private function textSet(): array
    {
        return [
            'display' => 'Text Block',
            'fields' => [
                ['handle' => 'text', 'field' => ['type' => 'textarea']],
            ],
        ];
    }

    private function bardSetNode(string $setHandle, array $values = []): array
    {
        return [
            'type' => 'set',
            'attrs' => [
                'id' => 'row-'.uniqid(),
                'values' => array_merge(['type' => $setHandle], $values),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // EntrySaving — Replicator
    // -------------------------------------------------------------------------

    public function test_new_replicator_sets_get_uuid_on_entry_saving(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorField('content', [
                'text_block' => $this->textSet(),
            ]),
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'content' => [
                ['type' => 'text_block', 'text' => 'Hello'],
                ['type' => 'text_block', 'text' => 'World'],
            ],
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $this->assertNotEmpty($data['content'][0]['_visual_id']);
        $this->assertNotEmpty($data['content'][1]['_visual_id']);
    }

    public function test_existing_replicator_set_uuid_is_preserved_on_entry_saving(): void
    {
        $existingUuid = 'existing-uuid-1234';

        $blueprint = $this->makeBlueprint([
            $this->replicatorField('content', [
                'text_block' => $this->textSet(),
            ]),
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'content' => [
                ['type' => 'text_block', 'text' => 'Hello', '_visual_id' => $existingUuid],
            ],
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $this->assertSame($existingUuid, $data['content'][0]['_visual_id']);
    }

    // -------------------------------------------------------------------------
    // EntrySaving — Bard
    // -------------------------------------------------------------------------

    public function test_new_bard_sets_get_uuid_on_entry_saving(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->bardField('body', [
                'text_block' => $this->textSet(),
            ]),
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'body' => [
                $this->bardSetNode('text_block', ['text' => 'Hello']),
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'plain text']]],
            ],
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $this->assertNotEmpty($data['body'][0]['attrs']['values']['_visual_id']);
        $this->assertArrayNotHasKey('_visual_id', $data['body'][1]);
    }

    public function test_existing_bard_set_uuid_is_preserved_on_entry_saving(): void
    {
        $existingUuid = 'bard-uuid-9876';

        $blueprint = $this->makeBlueprint([
            $this->bardField('body', [
                'text_block' => $this->textSet(),
            ]),
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'body' => [
                $this->bardSetNode('text_block', ['text' => 'Hello', '_visual_id' => $existingUuid]),
            ],
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $this->assertSame($existingUuid, $data['body'][0]['attrs']['values']['_visual_id']);
    }

    // -------------------------------------------------------------------------
    // Nested sets
    // -------------------------------------------------------------------------

    public function test_nested_replicator_in_bard_all_get_uuids(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->bardField('body', [
                'outer' => [
                    'display' => 'Outer',
                    'fields' => [
                        ['handle' => 'title', 'field' => ['type' => 'text']],
                        [
                            'handle' => 'nested_replicator',
                            'field' => [
                                'type' => 'replicator',
                                'sets' => [
                                    'inner_group' => [
                                        'display' => 'Inner',
                                        'sets' => [
                                            'inner' => $this->textSet(),
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'body' => [
                [
                    'type' => 'set',
                    'attrs' => [
                        'id' => 'outer-row',
                        'values' => [
                            'type' => 'outer',
                            'title' => 'Outer Title',
                            'nested_replicator' => [
                                ['type' => 'inner', 'text' => 'Inner text'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $outerValues = $data['body'][0]['attrs']['values'];

        $this->assertNotEmpty($outerValues['_visual_id']);
        $this->assertNotEmpty($outerValues['nested_replicator'][0]['_visual_id']);
    }

    // -------------------------------------------------------------------------
    // GlobalVariablesSaving
    // -------------------------------------------------------------------------

    public function test_new_replicator_sets_get_uuid_on_global_variables_saving(): void
    {
        $blueprint = $this->makeBlueprint([
            $this->replicatorField('content', [
                'text_block' => $this->textSet(),
            ]),
        ]);

        $variables = $this->makeTestObject($blueprint, [
            'content' => [
                ['type' => 'text_block', 'text' => 'Global value'],
            ],
        ]);

        GlobalVariablesSaving::dispatch($variables);

        $data = $variables->data()->all();

        $this->assertNotEmpty($data['content'][0]['_visual_id']);
    }

    public function test_non_replicator_bard_fields_are_not_modified(): void
    {
        $blueprint = $this->makeBlueprint([
            ['handle' => 'title', 'field' => ['type' => 'text']],
            ['handle' => 'slug', 'field' => ['type' => 'slug']],
        ]);

        $entry = $this->makeTestObject($blueprint, [
            'title' => 'Hello World',
            'slug' => 'hello-world',
        ]);

        EntrySaving::dispatch($entry);

        $data = $entry->data()->all();

        $this->assertSame('Hello World', $data['title']);
        $this->assertSame('hello-world', $data['slug']);
        $this->assertArrayNotHasKey('_visual_id', $data);
    }
}
