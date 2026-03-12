<?php

if (! function_exists('visual_edit')) {
  /**
   * Returns a data-sid attribute string for use in Blade templates during Live Preview.
   * Returns an empty string outside of Live Preview or when no UUID is provided.
   */
  function visual_edit(?string $uuid = null, ?string $label = null): string
  {
    if (! request()->isLivePreview()) {
      return '';
    }

    if (! $uuid) {
      return '';
    }

    $attr = 'data-sid="' . e($uuid) . '"';

    if ($label !== null && $label !== '') {
      $attr .= ' data-sid-label="' . e($label) . '"';
    }

    return $attr;
  }
}
