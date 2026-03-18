<?php

namespace Mariohamann\StatamicVisualEditor\Traits;

trait HandlesReplicatorSets
{
  /**
   * Find the field definitions for a given set handle, handling both flat and grouped formats.
   *
   * Flat format:    sets.{set_handle}.fields
   * Grouped format: sets.{group_name}.sets.{set_handle}.fields
   */
  protected function findSetFields(array $sets, string $setHandle): array
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

  /**
   * Iterate over all sets in both flat and grouped formats, applying a callback to each set's fields.
   *
   * The callback receives (array $fields): array and should return the modified fields array.
   * Returns the modified sets array.
   */
  protected function mapSetFields(array $sets, callable $callback): array
  {
    if (empty($sets)) {
      return $sets;
    }

    $firstValue = reset($sets);

    if (isset($firstValue['sets'])) {
      // Grouped format: sets.group_name.sets.set_name.fields
      foreach ($sets as $groupKey => $group) {
        foreach ($group['sets'] ?? [] as $setKey => $set) {
          $sets[$groupKey]['sets'][$setKey]['fields'] = $callback($set['fields'] ?? []);
        }
      }
    } else {
      // Flat format: sets.set_name.fields
      foreach ($sets as $setKey => $set) {
        $sets[$setKey]['fields'] = $callback($set['fields'] ?? []);
      }
    }

    return $sets;
  }
}
