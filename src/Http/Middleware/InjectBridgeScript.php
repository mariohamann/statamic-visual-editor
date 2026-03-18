<?php

namespace Mariohamann\StatamicVisualEditor\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectBridgeScript
{
  public function handle(Request $request, Closure $next): Response
  {
    $response = $next($request);

    if (! config('statamic-visual-editor.enabled', true)) {
      return $response;
    }

    if (! $this->isLivePreview($request)) {
      return $response;
    }

    if (! method_exists($response, 'getContent') || ! method_exists($response, 'setContent')) {
      return $response;
    }

    $content = $response->getContent();

    if ($content === false) {
      return $response;
    }

    $pos = strrpos($content, '</body>');

    if ($pos === false) {
      return $response;
    }

    $url = $this->resolveBridgeUrl();
    $tag = '<script type="module" src="' . e($url) . '"></script>';

    $response->setContent(substr_replace($content, $tag . '</body>', $pos, strlen('</body>')));

    return $response;
  }

  protected function isLivePreview(Request $request): bool
  {
    return $request->isLivePreview();
  }

  protected function resolveBridgeUrl(): string
  {
    $manifestPath = public_path('vendor/statamic-visual-editor/build/manifest.json');

    if (file_exists($manifestPath)) {
      $manifest = json_decode((string) file_get_contents($manifestPath), true);
      if (json_last_error() !== JSON_ERROR_NONE || ! is_array($manifest)) {
        return asset('vendor/statamic-visual-editor/bridge.js');
      }

      $entry = $manifest['resources/js/bridge.js'] ?? null;

      if ($entry && isset($entry['file'])) {
        return asset('vendor/statamic-visual-editor/build/' . $entry['file']);
      }
    }

    return asset('vendor/statamic-visual-editor/bridge.js');
  }
}
