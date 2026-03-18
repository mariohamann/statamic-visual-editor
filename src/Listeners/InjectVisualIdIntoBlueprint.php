<?php

namespace Mariohamann\StatamicVisualEditor\Listeners;

use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\GlobalVariablesBlueprintFound;
use Statamic\Facades\Fieldset;

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
        $result = [];

        foreach ($fields as $fieldDef) {
            // Expand fieldset imports inline so nested replicators/bards are reachable.
            if (isset($fieldDef['import'])) {
                $fieldset = Fieldset::find($fieldDef['import']);

                if ($fieldset) {
                    $result = array_merge($result, $this->processFields($fieldset->contents()['fields'] ?? []));
                } else {
                    $result[] = $fieldDef;
                }

                continue;
            }

            // Resolve string field references like `field: 'fieldset_handle.field_handle'`.
            if (isset($fieldDef['field']) && is_string($fieldDef['field'])) {
                $result[] = $this->resolveStringFieldRef($fieldDef);

                continue;
            }

            $type = $fieldDef['field']['type'] ?? null;

            if (in_array($type, ['replicator', 'bard'], true) && isset($fieldDef['field']['sets'])) {
                $fieldDef['field']['sets'] = $this->processReplicatorSets($fieldDef['field']['sets']);
            }

            if ($type === 'grid') {
                $gridFields = $fieldDef['field']['fields'] ?? [];
                $injected = $this->injectVisualId($gridFields);
                $fieldDef['field']['fields'] = $this->processFields($injected);
            }

            $result[] = $fieldDef;
        }

        return $result;
    }

    private function resolveStringFieldRef(array $fieldDef): array
    {
        $parts = explode('.', $fieldDef['field'], 2);

        if (count($parts) !== 2) {
            return $fieldDef;
        }

        [$fieldsetHandle, $fieldHandle] = $parts;
        $fieldset = Fieldset::find($fieldsetHandle);

        if (! $fieldset) {
            return $fieldDef;
        }

        foreach ($fieldset->contents()['fields'] ?? [] as $fsField) {
            if (($fsField['handle'] ?? null) !== $fieldHandle || ! is_array($fsField['field'])) {
                continue;
            }

            $inlined = $fsField;
            $inlined['handle'] = $fieldDef['handle'];
            $inlined['field'] = array_merge($fsField['field'], $fieldDef['config'] ?? []);

            $type = $inlined['field']['type'] ?? null;

            if (in_array($type, ['replicator', 'bard'], true) && isset($inlined['field']['sets'])) {
                $inlined['field']['sets'] = $this->processReplicatorSets($inlined['field']['sets']);
            }

            if ($type === 'grid') {
                $gridFields = $inlined['field']['fields'] ?? [];
                $injected = $this->injectVisualId($gridFields);
                $inlined['field']['fields'] = $this->processFields($injected);
            }

            return $inlined;
        }

        return $fieldDef;
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

        $fields[] = ['handle' => '_visual_id', 'field' => [
            'type' => 'auto_uuid',
            'visibility' => 'hidden',
            'replicator_preview' => false,
        ]];

        return $fields;
    }
}
