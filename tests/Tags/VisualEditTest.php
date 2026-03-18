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
    string $content = '',
    bool $isPair = false
  ): VisualEdit {
    $tag = new class($livePreview) extends VisualEdit
    {
      public function __construct(private bool $livePreviewEnabled) {}

      protected function isLivePreview(): bool
      {
        return $this->livePreviewEnabled;
      }

      public function parse($data = []): mixed
      {
        return $this->content;
      }
    };

    // Pair behaviour is driven by content being non-empty (mirrors Statamic's setContent()).
    $tag->setProperties([
      'parser' => null,
      'content' => $isPair ? ($content ?: 'pair-content') : $content,
      'context' => $context,
      'params' => $params,
      'tag' => 'visual_edit',
      'tag_method' => 'index',
    ]);

    return $tag;
  }

  // -------------------------------------------------------------------------
  // {{ visual_edit }} self-closing — Live Preview active
  // -------------------------------------------------------------------------

  public function test_selfclosing_outputs_data_sid_during_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
    );

    $this->assertSame('data-sid="abc-123"', $tag->index());
  }

  public function test_selfclosing_includes_label_from_type_in_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => 'text_block'],
      livePreview: true,
    );

    $this->assertSame('data-sid="abc-123" data-sid-label="Text Block" data-sid-type="text_block"', $tag->index());
  }

  public function test_selfclosing_includes_raw_type_as_data_sid_type(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => 'text'],
      livePreview: true,
    );

    $this->assertStringContainsString('data-sid-type="text"', $tag->index());
    $this->assertStringContainsString('data-sid-label="Text"', $tag->index());
  }

  public function test_selfclosing_explicit_id_param_overrides_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'from-context'],
      params: ['id' => 'from-param'],
      livePreview: true,
    );

    $this->assertStringContainsString('data-sid="from-param"', $tag->index());
  }

  public function test_selfclosing_omits_label_when_no_type_in_context(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('data-sid-label', $tag->index());
  }

  // -------------------------------------------------------------------------
  // {{ visual_edit }} self-closing — outside Live Preview / no UUID
  // -------------------------------------------------------------------------

  public function test_selfclosing_returns_empty_string_outside_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: false,
    );

    $this->assertSame('', $tag->index());
  }

  public function test_selfclosing_returns_empty_string_when_no_visual_id_in_context(): void
  {
    $tag = $this->makeTag(
      context: [],
      livePreview: true,
    );

    $this->assertSame('', $tag->index());
  }

  public function test_selfclosing_returns_empty_string_outside_live_preview_with_no_context(): void
  {
    $tag = $this->makeTag(livePreview: false);

    $this->assertSame('', $tag->index());
  }

  // -------------------------------------------------------------------------
  // {{ visual_edit }} self-closing — HTML escaping
  // -------------------------------------------------------------------------

  public function test_selfclosing_html_escapes_uuid(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => '"><script>'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('<script>', $tag->index());
    $this->assertStringContainsString('data-sid=', $tag->index());
  }

  public function test_selfclosing_html_escapes_label(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123', 'type' => '"><script>'],
      livePreview: true,
    );

    $this->assertStringNotContainsString('<script>', $tag->index());
    $this->assertStringContainsString('data-sid-label=', $tag->index());
  }

  // -------------------------------------------------------------------------
  // {{ visual_edit }}...{{ /visual_edit }} pair tag
  // -------------------------------------------------------------------------

  public function test_pair_wraps_content_in_div_during_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: true,
      content: 'hello world',
      isPair: true,
    );

    $result = $tag->index();

    $this->assertStringStartsWith('<div data-sid="abc-123">', $result);
    $this->assertStringEndsWith('</div>', $result);
    $this->assertStringContainsString('hello world', $result);
  }

  public function test_pair_passes_through_content_outside_live_preview(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'abc-123'],
      livePreview: false,
      content: 'hello world',
      isPair: true,
    );

    $this->assertSame('hello world', $tag->index());
  }

  public function test_pair_passes_through_content_when_no_visual_id(): void
  {
    $tag = $this->makeTag(
      context: [],
      livePreview: true,
      content: 'hello world',
      isPair: true,
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

  public function test_blade_helper_includes_type_when_provided(): void
  {
    Request::macro('isLivePreview', fn() => true);

    $this->assertSame('data-sid="abc-123" data-sid-label="Text" data-sid-type="text"', visual_edit('abc-123', 'text'));
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

  // -------------------------------------------------------------------------
  // {{ visual_edit }} self-closing — field= parameter (manual field targeting)
  // -------------------------------------------------------------------------

  public function test_selfclosing_with_field_param_outputs_data_sid_field(): void
  {
    $tag = $this->makeTag(
      livePreview: true,
      params: ['field' => 'hero_title'],
    );

    $this->assertSame('data-sid-field="hero_title"', $tag->index());
  }

  public function test_selfclosing_with_dot_notation_field_param_outputs_dot_notation(): void
  {
    $tag = $this->makeTag(
      livePreview: true,
      params: ['field' => 'page_info.author'],
    );

    $this->assertSame('data-sid-field="page_info.author"', $tag->index());
  }

  public function test_selfclosing_with_field_param_does_not_output_data_sid(): void
  {
    $tag = $this->makeTag(
      context: ['_visual_id' => 'some-uuid'],
      livePreview: true,
      params: ['field' => 'hero_title'],
    );

    $result = $tag->index();

    $this->assertStringContainsString('data-sid-field="hero_title"', $result);
    $this->assertStringNotContainsString('data-sid=', $result);
  }

  public function test_selfclosing_with_field_param_returns_empty_outside_live_preview(): void
  {
    $tag = $this->makeTag(
      livePreview: false,
      params: ['field' => 'hero_title'],
    );

    $this->assertSame('', $tag->index());
  }

  public function test_selfclosing_with_field_param_html_escapes_field_path(): void
  {
    $tag = $this->makeTag(
      livePreview: true,
      params: ['field' => '"><script>'],
    );

    $result = $tag->index();

    $this->assertStringNotContainsString('<script>', $result);
    $this->assertStringContainsString('data-sid-field=', $result);
  }

  // -------------------------------------------------------------------------
  // {{ visual_edit }}...{{ /visual_edit }} pair — field= parameter
  // -------------------------------------------------------------------------

  public function test_pair_with_field_param_wraps_content_with_data_sid_field(): void
  {
    $tag = $this->makeTag(
      livePreview: true,
      params: ['field' => 'hero_title'],
      content: 'My Hero Title',
      isPair: true,
    );

    $result = $tag->index();

    $this->assertStringContainsString('data-sid-field="hero_title"', $result);
    $this->assertStringContainsString('My Hero Title', $result);
    $this->assertStringStartsWith('<div ', $result);
    $this->assertStringEndsWith('</div>', $result);
  }

  public function test_pair_with_field_param_does_not_output_data_sid(): void
  {
    $tag = $this->makeTag(
      livePreview: true,
      params: ['field' => 'hero_title'],
      content: 'content',
      isPair: true,
    );

    $result = $tag->index();

    $this->assertStringNotContainsString('data-sid="', $result);
  }
}
