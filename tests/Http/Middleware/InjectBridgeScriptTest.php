<?php

namespace MarioHamann\StatamicVisualEditor\Tests\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use MarioHamann\StatamicVisualEditor\Http\Middleware\InjectBridgeScript;
use MarioHamann\StatamicVisualEditor\Tests\TestCase;

class InjectBridgeScriptTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMiddleware(bool $livePreview = true, string $bridgeUrl = 'http://localhost/bridge.js'): InjectBridgeScript
    {
        return new class($livePreview, $bridgeUrl) extends InjectBridgeScript
        {
            public function __construct(
                private bool $livePreviewEnabled,
                private string $url
            ) {}

            protected function isLivePreview(Request $request): bool
            {
                return $this->livePreviewEnabled;
            }

            protected function resolveBridgeUrl(): string
            {
                return $this->url;
            }
        };
    }

    private function makeRequest(): Request
    {
        return Request::create('/', 'GET');
    }

    private function makeHtmlResponse(string $html): Response
    {
        return new Response($html, 200, ['Content-Type' => 'text/html']);
    }

    // -------------------------------------------------------------------------
    // Injection during Live Preview
    // -------------------------------------------------------------------------

    public function test_middleware_injects_script_before_body_tag_during_live_preview(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringContainsString('<script', $response->getContent());
        $this->assertStringContainsString('</script></body>', $response->getContent());
    }

    public function test_middleware_injects_correct_bridge_url(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true, bridgeUrl: 'http://localhost/custom-bridge.js');
        $request = $this->makeRequest();
        $html = '<html><body></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringContainsString('http://localhost/custom-bridge.js', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // No injection outside Live Preview
    // -------------------------------------------------------------------------

    public function test_middleware_skips_injection_outside_live_preview(): void
    {
        $middleware = $this->makeMiddleware(livePreview: false);
        $request = $this->makeRequest();
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringNotContainsString('<script', $response->getContent());
        $this->assertSame($html, $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Missing </body> tag
    // -------------------------------------------------------------------------

    public function test_middleware_handles_response_without_body_tag(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<html><p>No body tag here</p></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringNotContainsString('<script', $response->getContent());
        $this->assertSame($html, $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Config toggle
    // -------------------------------------------------------------------------

    public function test_middleware_respects_config_toggle_when_disabled(): void
    {
        config(['statamic-visual-editor.enabled' => false]);

        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<html><body><p>Hello</p></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringNotContainsString('<script', $response->getContent());
        $this->assertSame($html, $response->getContent());
    }

    public function test_middleware_injects_when_config_enabled_explicitly(): void
    {
        config(['statamic-visual-editor.enabled' => true]);

        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<html><body></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringContainsString('<script', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Script tag attributes
    // -------------------------------------------------------------------------

    public function test_middleware_uses_module_script_type(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<html><body></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringContainsString('type="module"', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Last </body> replacement (strrpos robustness)
    // -------------------------------------------------------------------------

    public function test_middleware_injects_before_last_body_tag_when_multiple_exist(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        // Malformed HTML with two closing body tags — script must go before the last one.
        $html = '<html><body><!-- </body> fake --><p>Real</p></body></html>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));
        $content = $response->getContent();

        // The last </body> should be preceded by the script tag.
        $this->assertStringEndsWith('</script></body></html>', $content);
        // The fake </body> inside the comment must remain untouched.
        $this->assertStringContainsString('<!-- </body> fake -->', $content);
    }

    // -------------------------------------------------------------------------
    // Non-HTML responses
    // -------------------------------------------------------------------------

    public function test_middleware_does_not_modify_json_response_without_body_tag(): void
    {
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $json = '{"key":"value","nested":{"items":[1,2,3]}}';

        $jsonResponse = new Response($json, 200, ['Content-Type' => 'application/json']);
        $response = $middleware->handle($request, fn () => $jsonResponse);

        $this->assertSame($json, $response->getContent());
        $this->assertStringNotContainsString('<script', $response->getContent());
    }

    // -------------------------------------------------------------------------
    // Case sensitivity of </body> tag matching
    // -------------------------------------------------------------------------

    public function test_middleware_does_not_inject_when_body_closing_tag_is_uppercase(): void
    {
        // strrpos() is case-sensitive; uppercase </BODY> is not matched.
        // This test documents the known limitation: valid but uncommon HTML with
        // an uppercase closing tag will not have the bridge script injected.
        $middleware = $this->makeMiddleware(livePreview: true);
        $request = $this->makeRequest();
        $html = '<HTML><BODY><p>Hello</p></BODY></HTML>';

        $response = $middleware->handle($request, fn () => $this->makeHtmlResponse($html));

        $this->assertStringNotContainsString('<script', $response->getContent());
        $this->assertSame($html, $response->getContent());
    }
}
