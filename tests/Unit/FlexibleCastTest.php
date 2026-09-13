<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Povly\FlexibleLayouts\Casts\FlexibleCast;

final class FlexibleCastTest extends TestCase
{
    private FlexibleCast $cast;

    private Model $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cast = new FlexibleCast;
        $this->model = new class extends Model {};
    }

    public function test_get_returns_null_for_null_and_empty_string(): void
    {
        self::assertNull($this->cast->get($this->model, 'content', null, []));
        self::assertNull($this->cast->get($this->model, 'content', '', []));
    }

    public function test_get_decodes_json_array(): void
    {
        $result = $this->cast->get($this->model, 'content', '[{"_type":"hero","title":"Welcome"}]', []);

        self::assertSame([['_type' => 'hero', 'title' => 'Welcome']], $result);
    }

    public function test_get_passes_arrays_through(): void
    {
        $value = [['_type' => 'hero']];

        self::assertSame($value, $this->cast->get($this->model, 'content', $value, []));
    }

    public function test_get_returns_null_for_invalid_json(): void
    {
        self::assertNull($this->cast->get($this->model, 'content', '{"broken', []));
    }

    public function test_get_returns_null_for_non_array_json(): void
    {
        self::assertNull($this->cast->get($this->model, 'content', '"just a string"', []));
    }

    public function test_get_returns_null_when_depth_limit_exceeded(): void
    {
        $deepJson = str_repeat('[', 70).str_repeat(']', 70);

        self::assertNull($this->cast->get($this->model, 'content', $deepJson, []));
    }

    public function test_set_encodes_values_and_reindexes_keys(): void
    {
        $result = $this->cast->set($this->model, 'content', ['first' => ['_type' => 'hero']], []);

        self::assertSame(['content' => '[{"_type":"hero"}]'], $result);
    }

    public function test_set_converts_collections(): void
    {
        $result = $this->cast->set($this->model, 'content', collect([['_type' => 'hero']]), []);

        self::assertSame(['content' => '[{"_type":"hero"}]'], $result);
    }

    public function test_set_maps_null_and_empty_array_to_null(): void
    {
        self::assertSame(['content' => null], $this->cast->set($this->model, 'content', null, []));
        self::assertSame(['content' => null], $this->cast->set($this->model, 'content', [], []));
    }
}
