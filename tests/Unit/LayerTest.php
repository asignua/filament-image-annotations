<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Unit;

use Asignua\FilamentImageAnnotations\Support\Layer;
use PHPUnit\Framework\TestCase;

class LayerTest extends TestCase
{
    public function test_it_is_a_readonly_value_object_with_defaults(): void
    {
        $layer = new Layer('default', null, null, []);

        $this->assertSame('default', $layer->key);
        $this->assertFalse($layer->editable);
        $this->assertSame([], $layer->meta);
    }

    public function test_to_array_has_key_label_color_and_items(): void
    {
        $items = [['id' => 'a', 'type' => 'rect']];
        $layer = new Layer('u1', 'Anna', '#3b82f6', $items, true);

        $this->assertSame(['key' => 'u1', 'label' => 'Anna', 'color' => '#3b82f6', 'items' => $items], $layer->toArray());
    }

    public function test_meta_is_exported_only_when_present(): void
    {
        $layer = new Layer('u1', null, null, [], false, ['author_id' => 4]);

        $this->assertSame(['author_id' => 4], $layer->toArray()['meta']);
        $this->assertSame('[]', json_encode((new Layer('x', null, null, []))->toArray()['items']));
    }
}
