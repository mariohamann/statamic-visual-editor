<?php

namespace MarioHamann\StatamicVisualEditor\Listeners;

use MarioHamann\StatamicVisualEditor\Traits\HandlesReplicatorSets;
use Statamic\Events\EntrySaving;
use Statamic\Events\GlobalVariablesSaving;

class StripVisualIds
{
  use HandlesReplicatorSets;

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

      if (! $handle || ! isset($data[$handle]) || ! is_array($data[$handle])) {
        continue;
      }

      if ($type === 'grid') {
        $data[$handle] = $this->processGridItems(
          $data[$handle],
          $fieldDef['field']['fields'] ?? []
        );

        continue;
      }

      if (! in_array($type, ['replicator', 'bard'], true)) {
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

  private function processGridItems(array $rows, array $fieldDefs): array
  {
    foreach ($rows as $idx => $row) {
      if (! is_array($row)) {
        continue;
      }

      unset($rows[$idx]['_visual_id']);

      if (! empty($fieldDefs)) {
        $rows[$idx] = $this->processFieldsAgainstData($rows[$idx], $fieldDefs);
      }
    }

    return $rows;
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

      unset($items[$idx]['_visual_id']);

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

      unset($items[$idx]['attrs']['values']['_visual_id']);

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
}
