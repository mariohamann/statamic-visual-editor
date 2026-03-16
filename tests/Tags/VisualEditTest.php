<?php

namespace Mariohamann\StatamicVisualEditor\Tests\Tags;

use Illuminate\Http\Request;
use Mariohamann\StatamicVisualEditor\Tags\VisualEdit;
use Mariohamann\StatamicVisualEditor\Tests\TestCase;

class VisualEditTest extends TestCase
{
  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function makeTag(
    array $context = [],
    array $params = [],
    bool $livePreview = false,
    string $content = ''
  ): VisualEdit {
    $tag = new class($livePreview) extends VisualEdit
    {
      public function __construct(private bool $livePreviewEnabled) {}

      protected function isLivePreview(): bool
      {
        return $this->livePreviewEnabled;
      }
    };

    $tag->setProperties([
      'parser' => null,
      'content' => $content,
      'context' => $context,
      'params' => $params,
      'tag' => 'visual_edit',
      'tag_method' => 'index',
    ]);

    return $tag;
  }

  // -------------------------------------------------------------------------
  // attr() — Live Preview active
  // -------------------------------------------------------------------------

  public function test_attr_outputs_data_sid_during_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
    );

    $this->assertSame('data-sid="abc-123"', $tag->attr());
  }

  public function test_attr_includes_label_from_type_in_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => 'text_block'],
      livePreview: true,
    );

    $this->assertSame('data-sid="abc-123" data-sid-label="Text Block" data-sid-type="text_block"', $tag->attr());
  }

  public function test_attr_includes_raw_type_as_data_sid_type(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => 'text'],
      livePreview: true,
    );

    $this->assertStringContainsString('data-sid-type="text"', $tag->attr());
    $this->assertStringContainsString('data-sid-label="Text"', $tag->attr());
  }

  public function test_attr_explicit_id_param_overrides_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'from-context'],
      params: ['id' => 'from-param'],
      livePreview: true,
    );

    $this->assertStringContainsString('data-sid="from-param"', $tag->attr());
  }

  public function test_attr_explicit_label_param_overrides_type_in_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => 'context_type'],
      params: ['label' => 'explicit_label'],
      livePreview: true,
    );

    $this->assertStringContainsString('data-sid-label="explicit_label"', $tag->attr());
    $this->assertStringNotContainsString('data-sid-label="context_type"', $tag->attr());
    $this->assertStringNotContainsString('data-sid-label="Context Type"', $tag->attr());
  }

  public function test_attr_omits_label_when_no_type_in_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('data-sid-label', $tag->attr());
  }

  // -------------------------------------------------------------------------
  // attr() — outside Live Preview / no UUID
  // -------------------------------------------------------------------------

  public function test_attr_returns_empty_string_outside_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: false,
    );

    $this->assertSame('', $tag->attr());
  }

  public function test_attr_returns_empty_string_when_no_visual_id_in_context(): void
  {
    $tag = $this->makeTag(
      context: [],
      livePreview: true,
    );

    $this->assertSame('', $tag->attr());
  }

  public function test_attr_returns_empty_string_outside_live_preview_with_no_context(): void
  {
    $tag = $this->makeTag(livePreview: false);

    $this->assertSame('', $tag->attr());
  }

  // -------------------------------------------------------------------------
  // attr() — HTML escaping
  // -------------------------------------------------------------------------

  public function test_attr_html_escapes_uuid(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => '"><script>'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('<script>', $tag->attr());
    $this->assertStringContainsString('data-sid=', $tag->attr());
  }

  public function test_attr_html_escapes_label(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => '"><script>'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('<script>', $tag->attr());
    $this->assertStringContainsString('data-sid-label=', $tag->attr());
  }

  // -------------------------------------------------------------------------
  // index() pair tag
  // -------------------------------------------------------------------------

  public function test_index_wraps_content_in_div_during_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
      content: 'hello world',
    );

    $result = $tag->index();

    $this->assertStringStartsWith('<div data-sid="abc-123">', $result);
    $this->assertStringEndsWith('</div>', $result);
    $this->assertStringContainsString('hello world', $result);
  }

  public function test_index_passes_through_content_outside_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: false,
      content: 'hello world',
    );

    $this->assertSame('hello world', $tag->index());
  }

  public function test_index_passes_through_content_when_no_visual_id(): void
  {
    $tag = $this->makeTag(
      context: [],
      livePreview: true,
      content: 'hello world',
    );

    $this->assertSame('hello world', $tag->index());
  }

  // -------------------------------------------------------------------------
  // visual_edit() Blade helper
  // -------------------------------------------------------------------------

  public function test_blade_helper_returns_attr_string_during_live_preview(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertSame('data-sid="abc-123"', visual_edit('abc-123'));
  }

  public function test_blade_helper_returns_empty_string_outside_live_preview(): void
  {
    Request::macro('isLivePreview', fn() => false);

    $this->assertSame('', visual_edit('abc-123'));
  }

  public function test_blade_helper_returns_empty_string_when_uuid_is_null(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertSame('', visual_edit(null));
  }

  public function test_blade_helper_includes_label_when_provided(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertSame('data-sid="abc-123" data-sid-label="text_block"', visual_edit('abc-123', 'text_block'));
  }

  public function test_blade_helper_includes_type_when_provided(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertSame('data-sid="abc-123" data-sid-label="Text" data-sid-type="text"', visual_edit('abc-123', 'Text', 'text'));
  }

  public function test_blade_helper_omits_label_when_not_provided(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertStringNotContainsString('data-sid-label', visual_edit('abc-123'));
  }

  public function test_blade_helper_html_escapes_uuid(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $result = visual_edit('"><script>');

    $this->assertStringNotContainsString('<script>', $result);
    $this->assertStringContainsString('data-sid=', $result);
  }

  public function test_blade_helper_html_escapes_label(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $result = visual_edit('abc-123', '"><script>');

    $this->assertStringNotContainsString('<script>', $result);
    $this->assertStringContainsString('data-sid-label=', $result);
  }
}
