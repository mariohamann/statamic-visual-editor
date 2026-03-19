<?php

namespace MarioHamann\StatamicVisualEditor\Tests\Fieldtypes;

use MarioHamann\StatamicVisualEditor\Fieldtypes\AutoUuidFieldtype;
use MarioHamann\StatamicVisualEditor\Tests\TestCase;

class AutoUuidFieldtypeTest extends TestCase
{
    private AutoUuidFieldtype $fieldtype;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldtype = new AutoUuidFieldtype;
    }

    public function test_pre_process_generates_uuid_when_null(): void
    {
        $result = $this->fieldtype->preProcess(null);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $result
        );
    }

    public function test_pre_process_returns_existing_uuid_unchanged(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->fieldtype->preProcess($uuid);

        $this->assertSame($uuid, $result);
    }

    public function test_augment_passes_through(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $result = $this->fieldtype->augment($uuid);

        $this->assertSame($uuid, $result);
    }

    public function test_field_is_not_selectable_in_blueprint_picker(): void
    {
        $this->assertFalse($this->fieldtype->selectable());
    }
}
