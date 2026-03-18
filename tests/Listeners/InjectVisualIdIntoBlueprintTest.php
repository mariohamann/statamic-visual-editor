<?php

namespace Mariohamann\StatamicVisualEditor\Tests\Listeners;

use Mariohamann\StatamicVisualEditor\Tests\TestCase;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Facades\Fieldset;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Fieldset as FieldsetModel;

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

  private function replicatorWithFlatSets(array $sets): array
  {
    return [
      'handle' => 'content',
      'field' => [
        'type' => 'replicator',
        'sets' => $sets, // flat format: no group wrapper
      ],
    ];
  }

  private function getFlatSetFields(Blueprint $blueprint, string $setHandle): array
  {
    return $blueprint->contents()['tabs']['main']['sections'][0]['fields'][0]['field']['sets'][$setHandle]['fields'] ?? [];
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
    $visualIdCount = count(array_filter(array_column($fields, 'handle'), fn($h) => $h === '_visual_id'));

    $this->assertSame(1, $visualIdCount);
  }

  // -------------------------------------------------------------------------
  // Flat set format (non-grouped Replicator)
  // -------------------------------------------------------------------------

  public function test_flat_set_format_replicator_gains_visual_id(): void
  {
    // Flat format: sets are directly under 'sets' without a group wrapper.
    // mapSetFields() detects this when the first value has no 'sets' key.
    $blueprint = $this->makeBlueprint([
      $this->replicatorWithFlatSets([
        'text_block' => [
          'display' => 'Text Block',
          'fields' => [
            ['handle' => 'text', 'field' => ['type' => 'textarea']],
          ],
        ],
        'image' => [
          'display' => 'Image',
          'fields' => [
            ['handle' => 'src', 'field' => ['type' => 'text']],
          ],
        ],
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $textBlockFields = $this->getFlatSetFields($blueprint, 'text_block');
    $imageFields = $this->getFlatSetFields($blueprint, 'image');

    $this->assertContains('_visual_id', array_column($textBlockFields, 'handle'));
    $this->assertContains('_visual_id', array_column($imageFields, 'handle'));
  }

  public function test_flat_set_format_visual_id_not_duplicated_on_repeat_dispatch(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->replicatorWithFlatSets([
        'text_block' => [
          'display' => 'Text Block',
          'fields' => [
            ['handle' => 'text', 'field' => ['type' => 'textarea']],
          ],
        ],
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);
    EntryBlueprintFound::dispatch($blueprint);

    $fields = $this->getFlatSetFields($blueprint, 'text_block');
    $visualIdCount = count(array_filter(array_column($fields, 'handle'), fn($h) => $h === '_visual_id'));

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

  // -------------------------------------------------------------------------
  // String field references (field: 'fieldset_handle.field_handle')
  // -------------------------------------------------------------------------

  public function test_string_field_reference_resolves_nested_replicator_and_injects_visual_id(): void
  {
    // Build a minimal stand-in for the `buttons` fieldset: a replicator
    // field whose inner set has a plain label field (no fieldset imports),
    // keeping the mock self-contained.
    $mockFieldset = (new FieldsetModel)->setHandle('buttons')->setContents([
      'title' => 'Buttons',
      'fields' => [
        [
          'handle' => 'buttons',
          'field' => [
            'type' => 'replicator',
            'sets' => [
              'button' => [
                'display' => 'Button',
                'sets' => [
                  'button' => [
                    'display' => 'Button',
                    'fields' => [
                      ['handle' => 'label', 'field' => ['type' => 'text']],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);

    Fieldset::shouldReceive('find')
      ->andReturnUsing(fn($handle) => $handle === 'buttons' ? $mockFieldset : null);

    $blueprint = $this->makeBlueprint([
      $this->replicatorWithGroupedSets([
        'card' => [
          'display' => 'Card',
          'fields' => [
            ['handle' => 'heading', 'field' => ['type' => 'text']],
            // String field reference mirroring real-world `field: buttons.buttons`
            ['handle' => 'button', 'field' => 'buttons.buttons', 'config' => ['max_sets' => 1]],
          ],
        ],
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $cardFields = $this->getSetsFields($blueprint, 'content', 'main', 'card');

    // The card set must still receive _visual_id
    $this->assertContains('_visual_id', array_column($cardFields, 'handle'));

    // The string reference must be resolved to the inlined replicator
    $buttonField = collect($cardFields)->firstWhere('handle', 'button');
    $this->assertNotNull($buttonField, 'button field should be inlined from string reference');
    $this->assertSame('replicator', $buttonField['field']['type']);

    // Config overrides must be applied
    $this->assertSame(1, $buttonField['field']['max_sets']);

    // The inner button set must also receive _visual_id
    $innerButtonFields = $buttonField['field']['sets']['button']['sets']['button']['fields'] ?? [];
    $this->assertContains('_visual_id', array_column($innerButtonFields, 'handle'));
  }

  public function test_unresolvable_string_field_reference_is_kept_as_is(): void
  {
    Fieldset::shouldReceive('find')
      ->andReturn(null);

    $blueprint = $this->makeBlueprint([
      $this->replicatorWithGroupedSets([
        'card' => [
          'display' => 'Card',
          'fields' => [
            ['handle' => 'button', 'field' => 'nonexistent.field'],
          ],
        ],
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $cardFields = $this->getSetsFields($blueprint, 'content', 'main', 'card');

    // The unresolvable field must still appear (not silently dropped)
    $buttonField = collect($cardFields)->firstWhere('handle', 'button');
    $this->assertNotNull($buttonField);
    $this->assertSame('nonexistent.field', $buttonField['field']);
  }

  // -------------------------------------------------------------------------
  // Grid fieldtype
  // -------------------------------------------------------------------------

  private function gridField(string $handle, array $subFields = []): array
  {
    return [
      'handle' => $handle,
      'field' => [
        'type' => 'grid',
        'fields' => $subFields ?: [
          ['handle' => 'title', 'field' => ['type' => 'text']],
        ],
      ],
    ];
  }

  private function getGridFields(Blueprint $blueprint, string $fieldHandle): array
  {
    $fields = $blueprint->contents()['tabs']['main']['sections'][0]['fields'];

    foreach ($fields as $field) {
      if (($field['handle'] ?? null) === $fieldHandle) {
        return $field['field']['fields'] ?? [];
      }
    }

    return [];
  }

  public function test_grid_fields_gain_visual_id_on_entry_blueprint_found(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $fields = $this->getGridFields($blueprint, 'team_members');

    $this->assertContains('_visual_id', array_column($fields, 'handle'));
  }

  public function test_grid_visual_id_field_has_auto_uuid_type(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members'),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $fields = $this->getGridFields($blueprint, 'team_members');
    $visualIdField = collect($fields)->firstWhere('handle', '_visual_id');

    $this->assertNotNull($visualIdField);
    $this->assertSame('auto_uuid', $visualIdField['field']['type']);
    $this->assertSame('hidden', $visualIdField['field']['visibility']);
  }

  public function test_existing_grid_visual_id_is_not_duplicated(): void
  {
    $blueprint = $this->makeBlueprint([
      $this->gridField('team_members', [
        ['handle' => 'name', 'field' => ['type' => 'text']],
        ['handle' => '_visual_id', 'field' => ['type' => 'auto_uuid', 'visibility' => 'hidden', 'replicator_preview' => false]],
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);
    EntryBlueprintFound::dispatch($blueprint);

    $fields = $this->getGridFields($blueprint, 'team_members');
    $visualIdCount = count(array_filter(array_column($fields, 'handle'), fn($h) => $h === '_visual_id'));

    $this->assertSame(1, $visualIdCount);
  }

  public function test_replicator_inside_grid_row_also_gains_visual_id(): void
  {
    $nestedReplicator = [
      'handle' => 'items',
      'field' => [
        'type' => 'replicator',
        'sets' => [
          'item' => [
            'display' => 'Item',
            'sets' => [
              'item' => [
                'display' => 'Item',
                'fields' => [
                  ['handle' => 'label', 'field' => ['type' => 'text']],
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    $blueprint = $this->makeBlueprint([
      $this->gridField('rows', [
        ['handle' => 'name', 'field' => ['type' => 'text']],
        $nestedReplicator,
      ]),
    ]);

    EntryBlueprintFound::dispatch($blueprint);

    $gridFields = $this->getGridFields($blueprint, 'rows');

    // Grid itself must get _visual_id
    $this->assertContains('_visual_id', array_column($gridFields, 'handle'));

    // Nested replicator sets must also get _visual_id
    $replicatorField = collect($gridFields)->firstWhere('handle', 'items');
    $innerSetFields = $replicatorField['field']['sets']['item']['sets']['item']['fields'] ?? [];
    $this->assertContains('_visual_id', array_column($innerSetFields, 'handle'));
  }
}
