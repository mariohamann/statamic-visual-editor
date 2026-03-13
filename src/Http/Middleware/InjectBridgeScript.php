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

        if (! str_contains($content, '</body>')) {
            return $response;
        }

        $url = $this->resolveBridgeUrl();
        $tag = '<script type="module" src="'.e($url).'"></script>';

        $response->setContent(str_replace('</body>', $tag.'</body>', $content));

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
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $entry = $manifest['resources/js/bridge.js'] ?? null;

            if ($entry && isset($entry['file'])) {
                return asset('vendor/statamic-visual-editor/build/'.$entry['file']);
            }
        }

        return asset('vendor/statamic-visual-editor/bridge.js');
    }
}
