<?php

namespace MarioHamann\StatamicVisualEditor\Tags;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Statamic\Facades\Blueprint;
use Statamic\Tags\Tags;

class VisualEdit extends Tags
{
    protected static $handle = 'visual_edit';

    /**
     * {{ visual_edit }} — Dual-mode tag.
     *
     * Self-closing: returns the data-sid attribute string for inline use inside an HTML opening tag.
     * Pair tag: wraps content in a <div> with data-sid attributes.
     *
     * No-op outside Live Preview or when no UUID/field is available.
     *
     * With `field="dot.separated.path"`: targets a specific CP field by handle.
     * With no `field` param: targets the nearest Replicator/Bard/Grid set UUID.
     */
    public function index(): string
    {
        $isPair = $this->isPair;
        $content = $isPair ? (string) $this->parse() : '';

        if (! $this->isLivePreview()) {
            return $content;
        }

        $field = $this->params->get('field');
        $inside = $this->params->bool('outline-inside', false);

        if ($field !== null && (string) $field !== '') {
            $attr = $this->buildFieldAttr((string) $field, $this->resolveFieldLabel((string) $field), $inside);

            return $isPair ? '<div '.$attr.'>'.$content.'</div>' : $attr;
        }

        $uuid = $this->params->get('id', $this->context->get('_visual_id'));

        if (! $uuid) {
            return $content;
        }

        $attr = $this->buildAttr((string) $uuid, $this->resolveLabel(), $this->resolveType(), $inside);

        return $isPair ? '<div '.$attr.'>'.$content.'</div>' : $attr;
    }

    private function resolveLabel(): string
    {
        $type = (string) $this->context->get('type', '');

        return $type ? Str::headline($type) : '';
    }

    private function resolveFieldLabel(string $fieldPath): string
    {
        $blueprintHandle = $this->params->get('blueprint');

        if ($blueprintHandle) {
            $blueprint = Blueprint::find((string) $blueprintHandle);
        } else {
            $page = $this->context->get('page');

            if (! $page || ! method_exists($page, 'blueprint')) {
                return '';
            }

            $blueprint = $page->blueprint();
        }

        if (! $blueprint) {
            return '';
        }

        try {
            $fields = $blueprint->fields()->all();
            $segments = explode('.', $fieldPath);
            $firstHandle = array_shift($segments);

            $field = $fields->get($firstHandle);

            if (! $field) {
                return '';
            }

            if (empty($segments)) {
                return $field->display();
            }

            foreach ($field->config()['fields'] ?? [] as $subConfig) {
                if (($subConfig['handle'] ?? '') === $segments[0]) {
                    return $subConfig['field']['display'] ?? '';
                }
            }
        } catch (\InvalidArgumentException|\BadMethodCallException $e) {
            Log::debug('VisualEdit: failed to resolve field label for '.$fieldPath, ['exception' => $e]);

            return '';
        }

        return '';
    }

    private function resolveType(): string
    {
        return (string) $this->context->get('type', '');
    }

    private function buildFieldAttr(string $fieldPath, string $label, bool $inside = false): string
    {
        $attr = 'data-sid-field="'.e($fieldPath).'"';

        if ($label !== '') {
            $attr .= ' data-sid-label="'.e($label).'"';
        }

        if ($inside) {
            $attr .= ' data-sid-inside';
        }

        return $attr;
    }

    private function buildAttr(string $uuid, string $label, string $type = '', bool $inside = false): string
    {
        $attr = 'data-sid="'.e($uuid).'"';

        if ($label !== '') {
            $attr .= ' data-sid-label="'.e($label).'"';
        }

        if ($type !== '') {
            $attr .= ' data-sid-type="'.e($type).'"';
        }

        if ($inside) {
            $attr .= ' data-sid-inside';
        }

        return $attr;
    }

    protected function isLivePreview(): bool
    {
        return request()->isLivePreview();
    }
}
