<?php

namespace Mariohamann\StatamicVisualEditor\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;

class InjectVisualIdIntoBlueprint
{
  public function handle(EntryBlueprintFound|GlobalVariablesBlueprintFound $event): void
  {
    $contents = $event->blueprint->contents();
    $contents = $this->processContents($contents);
    $event->blueprint->setContents($contents);
  }

  private function processContents(array $contents): array
  {
    foreach ($contents['tabs'] ?? [] as $tabKey => $tab) {
      foreach ($tab['sections'] ?? [] as $sectionIdx => $section) {
        $contents['tabs'][$tabKey]['sections'][$sectionIdx]['fields'] =
          $this->processFields($section['fields'] ?? []);
      }
    }

    return $contents;
  }

  private function processFields(array $fields): array
  {
    foreach ($fields as $idx => $fieldDef) {
      $type = $fieldDef['field']['type'] ?? null;

      if (in_array($type, ['replicator', 'bard'], true) && isset($fieldDef['field']['sets'])) {
        $fields[$idx]['field']['sets'] = $this->processReplicatorSets($fieldDef['field']['sets']);
      }
    }

    return $fields;
  }

  private function processReplicatorSets(array $sets): array
  {
    if (empty($sets)) {
      return $sets;
    }

    $firstValue = reset($sets);

    if (isset($firstValue['sets'])) {
      // Grouped format: sets.group_name.sets.set_name.fields
      foreach ($sets as $groupKey => $group) {
        foreach ($group['sets'] ?? [] as $setKey => $set) {
          $injected = $this->injectVisualId($set['fields'] ?? []);
          $sets[$groupKey]['sets'][$setKey]['fields'] = $this->processFields($injected);
        }
      }
    } else {
      // Flat format: sets.set_name.fields
      foreach ($sets as $setKey => $set) {
        $injected = $this->injectVisualId($set['fields'] ?? []);
        $sets[$setKey]['fields'] = $this->processFields($injected);
      }
    }

    return $sets;
  }

  private function injectVisualId(array $fields): array
  {
    $handles = array_column($fields, 'handle');

    if (in_array('_visual_id', $handles, true)) {
      return $fields;
    }

    $fields[] = ['handle' => '_visual_id', 'field' => ['type' => 'auto_uuid']];

    return $fields;
  }
}
