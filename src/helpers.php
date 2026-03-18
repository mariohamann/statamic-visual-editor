<?php

use Illuminate\Support\Str;

if (! function_exists('visual_edit')) {
    /**
     * Returns a data-sid attribute string for use in Blade templates during Live Preview.
     * Returns an empty string outside of Live Preview or when no UUID is provided.
     */
    function visual_edit(?string $uuid = null, ?string $type = null): string
    {
        if (! request()->isLivePreview()) {
            return '';
        }

        if (! $uuid) {
            return '';
        }

        $attr = 'data-sid="'.e($uuid).'"';

        $label = ($type !== null && $type !== '') ? Str::headline($type) : '';

        if ($label !== '') {
            $attr .= ' data-sid-label="'.e($label).'"';
        }

        if ($type !== null && $type !== '') {
            $attr .= ' data-sid-type="'.e($type).'"';
        }

        return $attr;
    }
}
