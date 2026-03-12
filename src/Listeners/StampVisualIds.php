<?php

namespace Mariohamann\StatamicVisualEditor\Listeners;

use Illuminate\Support\Str;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;

class StampVisualIds
{
  public function handle(EntrySaving|GlobalVariablesSaving $event): void
  {
    $object = isset($event->entry) ? $event->entry : $event->variables;
    $contents = $object->blueprint()->contents();

    $data = $object->data()->all();
    $data = $this->processDataWithContents($data, $contents);
    $object->data($data);
  }

  private function processDataWithContents(array $data, array $contents): array
  {
    foreach ($contents['tabs'] ?? [] as $tab) {
      foreach ($tab['sections'] ?? [] as $section) {
        $data = $this->processFieldsAgainstData($data, $section['fields'] ?? []);
      }
    }

    return $data;
  }

  private function processFieldsAgainstData(array $data, array $fieldDefs): array
  {
    foreach ($fieldDefs as $fieldDef) {
      $handle = $fieldDef['handle'] ?? null;
      $type = $fieldDef['field']['type'] ?? null;

      if (! in_array($type, ['replicator', 'bard'], true)) {
        continue;
      }

      if (! $handle || ! isset($data[$handle]) || ! is_array($data[$handle])) {
        continue;
      }

      $data[$handle] = $this->processFieldValue(
        $data[$handle],
        $type,
        $fieldDef['field']['sets'] ?? []
      );
    }

    return $data;
  }

  private function processFieldValue(array $items, string $fieldType, array $sets): array
  {
    if ($fieldType === 'bard') {
      return $this->processBardItems($items, $sets);
    }

    return $this->processReplicatorItems($items, $sets);
  }

  private function processReplicatorItems(array $items, array $sets): array
  {
    foreach ($items as $idx => $item) {
      if (! is_array($item) || ! isset($item['type'])) {
        continue;
      }

      if (empty($item['_visual_id'])) {
        $items[$idx]['_visual_id'] = (string) Str::uuid();
      }

      $setFields = $this->findSetFields($sets, $item['type']);

      if (! empty($setFields)) {
        $items[$idx] = $this->processFieldsAgainstData($items[$idx], $setFields);
      }
    }

    return $items;
  }

  private function processBardItems(array $items, array $sets): array
  {
    foreach ($items as $idx => $item) {
      if (! is_array($item) || ($item['type'] ?? null) !== 'set' || ! isset($item['attrs']['values'])) {
        continue;
      }

      if (empty($item['attrs']['values']['_visual_id'])) {
        $items[$idx]['attrs']['values']['_visual_id'] = (string) Str::uuid();
      }

      $setHandle = $item['attrs']['values']['type'] ?? null;

      if ($setHandle) {
        $setFields = $this->findSetFields($sets, $setHandle);

        if (! empty($setFields)) {
          $items[$idx]['attrs']['values'] = $this->processFieldsAgainstData(
            $items[$idx]['attrs']['values'],
            $setFields
          );
        }
      }
    }

    return $items;
  }

  private function findSetFields(array $sets, string $setHandle): array
  {
    // Flat format: sets.set_handle.fields
    if (isset($sets[$setHandle]['fields'])) {
      return $sets[$setHandle]['fields'];
    }

    // Grouped format: sets.group_name.sets.set_handle.fields
    foreach ($sets as $group) {
      if (is_array($group) && isset($group['sets'][$setHandle]['fields'])) {
        return $group['sets'][$setHandle]['fields'];
      }
    }

    return [];
  }
}
