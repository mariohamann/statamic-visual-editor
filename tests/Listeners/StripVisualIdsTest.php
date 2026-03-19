<?php

namespace MarioHamann\StatamicVisualEditor\Tests\Listeners;

use MarioHamann\StatamicVisualEditor\Tests\TestCase;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;
use Statamic\Fields\Blueprint;

class StripVisualIdsTest extends TestCase
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
        'id' => 'row-' . uniqid(),
        'values' => array_merge(['type' => $setHandle], $values),
      ],
    ];
  }

  // -------------------------------------------------------------------------
  // EntrySaving — Replicator
  // -------------------------------------------------------------------------

  public function test_replicator_visual_ids_are_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->replicatorField('content', [
        'text_block' => $this->textSet(),
      ]),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'content' => [
        ['type' => 'text_block', 'text' => 'Hello', '_visual_id' => 'existing-uuid-1'],
        ['type' => 'text_block', 'text' => 'World'],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['content'][0]);
    $this->assertArrayNotHasKey('_visual_id', $data['content'][1]);
  }

  public function test_existing_replicator_visual_id_is_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->replicatorField('content', [
        'text_block' => $this->textSet(),
      ]),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'content' => [
        ['type' => 'text_block', 'text' => 'Hello', '_visual_id' => 'some-existing-uuid'],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['content'][0]);
  }

  // -------------------------------------------------------------------------
  // EntrySaving — Bard
  // -------------------------------------------------------------------------

  public function test_bard_set_visual_ids_are_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->bardField('body', [
        'text_block' => $this->textSet(),
      ]),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'body' => [
        $this->bardSetNode('text_block', ['text' => 'Hello', '_visual_id' => 'bard-uuid-1']),
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'plain text']]],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['body'][0]['attrs']['values']);
    $this->assertArrayNotHasKey('_visual_id', $data['body'][1]);
  }

  public function test_existing_bard_visual_id_is_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->bardField('body', [
        'text_block' => $this->textSet(),
      ]),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'body' => [
        $this->bardSetNode('text_block', ['text' => 'Hello', '_visual_id' => 'bard-uuid-9876']),
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['body'][0]['attrs']['values']);
  }

  // -------------------------------------------------------------------------
  // Nested sets
  // -------------------------------------------------------------------------

  public function test_nested_replicator_in_bard_visual_ids_are_all_stripped(): void
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
              '_visual_id' => 'outer-uuid',
              'nested_replicator' => [
                ['type' => 'inner', 'text' => 'Inner text', '_visual_id' => 'inner-uuid'],
              ],
            ],
          ],
        ],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $outerValues = $data['body'][0]['attrs']['values'];

    $this->assertArrayNotHasKey('_visual_id', $outerValues);
    $this->assertArrayNotHasKey('_visual_id', $outerValues['nested_replicator'][0]);
  }

  // -------------------------------------------------------------------------
  // GlobalVariablesSaving
  // -------------------------------------------------------------------------

  public function test_replicator_visual_ids_are_stripped_on_global_variables_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->replicatorField('content', [
        'text_block' => $this->textSet(),
      ]),
    ]);

    $variables = $this->makeTestObject($blueprint, [
      'content' => [
        ['type' => 'text_block', 'text' => 'Global value', '_visual_id' => 'global-uuid'],
      ],
    ]);

    GlobalVariablesSaving::dispatch($variables);

    $data = $variables->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['content'][0]);
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

  // -------------------------------------------------------------------------
  // EntrySaving — Grid
  // -------------------------------------------------------------------------

  private function gridField(string $handle, array $subFields = []): array
  {
    return [
      'handle' => $handle,
      'field' => [
        'type' => 'grid',
        'fields' => $subFields ?: [
          ['handle' => 'name', 'field' => ['type' => 'text']],
          ['handle' => '_visual_id', 'field' => ['type' => 'auto_uuid', 'visibility' => 'hidden', 'replicator_preview' => false]],
        ],
      ],
    ];
  }

  public function test_grid_row_visual_ids_are_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'team_members' => [
        ['name' => 'Alice', '_visual_id' => 'grid-uuid-alice'],
        ['name' => 'Bob', '_visual_id' => 'grid-uuid-bob'],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['team_members'][0]);
    $this->assertArrayNotHasKey('_visual_id', $data['team_members'][1]);
  }

  public function test_existing_grid_visual_id_is_stripped_on_entry_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'team_members' => [
        ['name' => 'Alice', '_visual_id' => 'grid-uuid-abc123'],
        ['name' => 'Bob'],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['team_members'][0]);
    $this->assertArrayNotHasKey('_visual_id', $data['team_members'][1]);
  }

  public function test_replicator_nested_in_grid_row_visual_ids_are_stripped(): void
  {
    $nestedReplicator = [
      'handle' => 'items',
      'field' => [
        'type' => 'replicator',
        'sets' => [
          'item' => [
            'display' => 'Item',
            'sets' => [
              'item' => $this->textSet(),
            ],
          ],
        ],
      ],
    ];

    $blueprint = $this->makeBlueprint([
      [
        'handle' => 'rows',
        'field' => [
          'type' => 'grid',
          'fields' => [
            ['handle' => 'name', 'field' => ['type' => 'text']],
            ['handle' => '_visual_id', 'field' => ['type' => 'auto_uuid', 'visibility' => 'hidden', 'replicator_preview' => false]],
            $nestedReplicator,
          ],
        ],
      ],
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'rows' => [
        [
          'name' => 'Row A',
          '_visual_id' => 'grid-row-uuid',
          'items' => [
            ['type' => 'item', 'text' => 'Item 1', '_visual_id' => 'item-uuid'],
          ],
        ],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['rows'][0]);
    $this->assertArrayNotHasKey('_visual_id', $data['rows'][0]['items'][0]);
  }

  public function test_empty_grid_value_is_not_modified(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'team_members' => [],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertSame([], $data['team_members']);
  }

  public function test_grid_visual_ids_are_stripped_on_global_variables_saving(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    $variables = $this->makeTestObject($blueprint, [
      'team_members' => [
        ['name' => 'Global Member', '_visual_id' => 'global-grid-uuid'],
      ],
    ]);

    GlobalVariablesSaving::dispatch($variables);

    $data = $variables->data()->all();

    $this->assertArrayNotHasKey('_visual_id', $data['team_members'][0]);
  }

  // -------------------------------------------------------------------------
  // Deeply nested: Bard → Replicator → Bard
  // -------------------------------------------------------------------------

  public function test_three_level_bard_replicator_bard_nesting_visual_ids_are_all_stripped(): void
  {
    // Blueprint: outer Bard → outer_set (has inner_rep: Replicator) → inner_rep_set (has inner_bard: Bard) → innermost_set
    $blueprint = $this->makeBlueprint([
      $this->bardField('body', [
        'outer_set' => [
          'display' => 'Outer Set',
          'fields' => [
            ['handle' => 'title', 'field' => ['type' => 'text']],
            [
              'handle' => 'inner_rep',
              'field' => [
                'type' => 'replicator',
                'sets' => [
                  'inner_group' => [
                    'display' => 'Inner Group',
                    'sets' => [
                      'inner_rep_set' => [
                        'display' => 'Inner Rep Set',
                        'fields' => [
                          ['handle' => 'label', 'field' => ['type' => 'text']],
                          [
                            'handle' => 'inner_bard',
                            'field' => [
                              'type' => 'bard',
                              'sets' => [
                                'innermost_group' => [
                                  'display' => 'Innermost Group',
                                  'sets' => [
                                    'innermost_set' => [
                                      'display' => 'Innermost Set',
                                      'fields' => [
                                        ['handle' => 'text', 'field' => ['type' => 'textarea']],
                                      ],
                                    ],
                                  ],
                                ],
                              ],
                            ],
                          ],
                        ],
                      ],
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
            'id' => 'outer-bard-row',
            'values' => [
              'type' => 'outer_set',
              'title' => 'Outer Title',
              '_visual_id' => 'outer-bard-uuid',
              'inner_rep' => [
                [
                  'type' => 'inner_rep_set',
                  'label' => 'Inner Label',
                  '_visual_id' => 'inner-rep-uuid',
                  'inner_bard' => [
                    [
                      'type' => 'set',
                      'attrs' => [
                        'id' => 'innermost-bard-row',
                        'values' => [
                          'type' => 'innermost_set',
                          'text' => 'Innermost text',
                          '_visual_id' => 'innermost-bard-uuid',
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    // Level 1: outer Bard set
    $outerValues = $data['body'][0]['attrs']['values'];
    $this->assertArrayNotHasKey('_visual_id', $outerValues, 'outer Bard set should not have _visual_id');

    // Level 2: inner Replicator set nested inside the outer Bard set
    $innerRepSet = $outerValues['inner_rep'][0];
    $this->assertArrayNotHasKey('_visual_id', $innerRepSet, 'inner Replicator set should not have _visual_id');

    // Level 3: innermost Bard set nested inside the inner Replicator set
    $innermostValues = $innerRepSet['inner_bard'][0]['attrs']['values'];
    $this->assertArrayNotHasKey('_visual_id', $innermostValues, 'innermost Bard set should not have _visual_id');
  }

  // -------------------------------------------------------------------------
  // Non-replicator/bard/grid fields remain unchanged
  // -------------------------------------------------------------------------

  public function test_plain_scalar_fields_adjacent_to_replicator_are_not_modified(): void
  {
    $blueprint = $this->makeBlueprint([
      ['handle' => 'title', 'field' => ['type' => 'text']],
      ['handle' => 'slug', 'field' => ['type' => 'slug']],
      ['handle' => 'hero_image', 'field' => ['type' => 'assets', 'max_files' => 1]],
      $this->replicatorField('items', ['item' => $this->textSet()]),
    ]);

    $entry = $this->makeTestObject($blueprint, [
      'title' => 'My Title',
      'slug' => 'my-title',
      'hero_image' => 'photo.jpg',
      'items' => [
        ['type' => 'item', 'text' => 'Hello', '_visual_id' => 'item-uuid'],
      ],
    ]);

    EntrySaving::dispatch($entry);

    $data = $entry->data()->all();

    $this->assertSame('My Title', $data['title']);
    $this->assertSame('my-title', $data['slug']);
    $this->assertSame('photo.jpg', $data['hero_image']);
    $this->assertArrayNotHasKey('_visual_id', $data);
    $this->assertArrayNotHasKey('_visual_id', $data['items'][0]);
  }
}
