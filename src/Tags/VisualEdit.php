<?php

namespace Mariohamann\StatamicVisualEditor\Tags;

use Illuminate\Support\Str;
use Statamic\Tags\Tags;

class VisualEdit extends Tags
{
  protected static $handle = 'visual_edit';

  /**
   * {{ visual_edit:attr }} — Returns the data-sid attribute string for inline use.
   * No-op outside Live Preview or when no UUID is available.
   */
  public function attr(): string
  {
    if (! $this->isLivePreview()) {
      return '';
    }

    $uuid = $this->params->get('id', $this->context->get('_visual_id'));

    if (! $uuid) {
      return '';
    }

    return $this->buildAttr((string) $uuid, $this->resolveLabel());
  }

  /**
   * {{ visual_edit }}...{{ /visual_edit }} — Wraps content in a div with data-sid.
   * No-op (passes through content) outside Live Preview or when no UUID is available.
   */
  public function index(): string
  {
    $content = $this->canParseContents() ? (string) $this->parse() : $this->content;

    if (! $this->isLivePreview()) {
      return $content;
    }

    $uuid = $this->params->get('id', $this->context->get('_visual_id'));

    if (! $uuid) {
      return $content;
    }

    return '<div ' . $this->buildAttr((string) $uuid, $this->resolveLabel()) . '>' . $content . '</div>';
  }

  private function resolveLabel(): string
  {
    $label = $this->params->get('label');

    if ($label !== null) {
      return (string) $label;
    }

    $type = (string) $this->context->get('type', '');

    return $type ? Str::headline($type) : '';
  }

  private function buildAttr(string $uuid, string $label): string
  {
    $attr = 'data-sid="' . e($uuid) . '"';

    if ($label !== '') {
      $attr .= ' data-sid-label="' . e($label) . '"';
    }

    return $attr;
  }

  protected function isLivePreview(): bool
  {
    return request()->isLivePreview();
  }
}
