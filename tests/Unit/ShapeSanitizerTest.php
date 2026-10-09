<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Unit;

use Asignua\FilamentImageAnnotations\Support\ShapeSanitizer;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ShapeSanitizerTest extends TestCase
{
    private const array EMPTY = ['v' => 1, 'items' => []];

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function rect(array $override = []): array
    {
        return array_merge(['id' => 'a1', 'type' => 'rect', 'pts' => [[0.1, 0.2], [0.5, 0.6]], 'color' => 'red', 'width' => 4], $override);
    }

    /**
     * @param array<string, mixed> ...$items
     *
     * @return list<array<string, mixed>>
     */
    private function items(array ...$items): array
    {
        return ShapeSanitizer::sanitize(['v' => 1, 'items' => $items])['items'];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function junk(): iterable
    {
        yield 'null' => [null];
        yield 'int' => [42];
        yield 'bool' => [true];
        yield 'empty string' => [''];
        yield 'junk json' => ['{not json'];
        yield 'json scalar' => ['"hello"'];
        yield 'no items' => [['v' => 1]];
        yield 'items not array' => [['items' => 'x']];
        yield 'object' => [new \stdClass];
    }

    #[DataProvider('junk')]
    public function test_bad_input_gives_an_empty_layer(mixed $input): void
    {
        $this->assertSame(self::EMPTY, ShapeSanitizer::sanitize($input));
    }

    public function test_accepts_a_json_string(): void
    {
        $result = ShapeSanitizer::sanitize(json_encode(['v' => 1, 'items' => [$this->rect()]]));

        $this->assertCount(1, $result['items']);
        $this->assertSame('rect', $result['items'][0]['type']);
    }

    public function test_a_valid_item_is_normalised(): void
    {
        $this->assertSame([[
            'id' => 'a1',
            'type' => 'rect',
            'pts' => [[0.1, 0.2], [0.5, 0.6]],
            'color' => '#ef4444',
            'width' => 4,
        ]], $this->items($this->rect()));
    }

    public function test_unknown_types_and_non_arrays_are_dropped(): void
    {
        $this->assertSame([], $this->items($this->rect(['type' => 'star']), $this->rect(['type' => ['rect']]), ['pts' => [[0, 0], [1, 1]]]));
        $this->assertSame([], ShapeSanitizer::sanitize(['items' => ['rect', 5, null]])['items']);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function twoPointTypes(): iterable
    {
        foreach (['arrow', 'line', 'rect', 'ellipse'] as $type) {
            yield $type.' with 1 point' => [$type, 1];
            yield $type.' with 3 points' => [$type, 3];
        }
    }

    #[DataProvider('twoPointTypes')]
    public function test_shapes_need_exactly_two_points(string $type, int $count): void
    {
        $pts = array_fill(0, $count, [0.5, 0.5]);

        $this->assertSame([], $this->items($this->rect(['type' => $type, 'pts' => $pts])));
        $this->assertCount(1, $this->items($this->rect(['type' => $type, 'pts' => [[0, 0], [1, 1]]])));
    }

    public function test_pen_and_highlight_take_two_up_to_the_point_limit(): void
    {
        config(['filament-image-annotations.limits.points' => 5]);

        foreach (['pen', 'highlight'] as $type) {
            $this->assertSame([], $this->items($this->rect(['type' => $type, 'pts' => [[0, 0]]])), $type.' with 1');
            $this->assertCount(1, $this->items($this->rect(['type' => $type, 'pts' => array_fill(0, 2, [0, 0])])), $type.' with 2');
            $this->assertCount(1, $this->items($this->rect(['type' => $type, 'pts' => array_fill(0, 5, [0, 0])])), $type.' with 5');
            $this->assertSame([], $this->items($this->rect(['type' => $type, 'pts' => array_fill(0, 6, [0, 0])])), $type.' with 6');
        }
    }

    public function test_text_needs_one_point_and_non_empty_text(): void
    {
        $text = fn (array $o = []): array => $this->rect(array_merge(['type' => 'text', 'pts' => [[0.3, 0.3]], 'text' => 'Hi'], $o));

        $this->assertSame('Hi', $this->items($text())[0]['text']);
        $this->assertSame([], $this->items($text(['pts' => [[0, 0], [1, 1]]])));
        $this->assertSame([], $this->items($text(['text' => ''])));
        $this->assertSame([], $this->items($text(['text' => "  \n\t "])));
        $this->assertSame([], $this->items($text(['text' => null])));
        $this->assertSame([], $this->items($text(['text' => ['a']])));
    }

    public function test_text_is_only_kept_on_text_items(): void
    {
        $this->assertArrayNotHasKey('text', $this->items($this->rect(['text' => 'hello']))[0]);
    }

    public function test_html_in_text_stays_a_literal_string(): void
    {
        $item = $this->items($this->rect(['type' => 'text', 'pts' => [[0.3, 0.3]], 'text' => '<script>alert(1)</script>']))[0];

        $this->assertSame('<script>alert(1)</script>', $item['text']);
    }

    public function test_text_control_characters_are_stripped_but_newlines_stay(): void
    {
        $item = $this->items($this->rect(['type' => 'text', 'pts' => [[0.3, 0.3]], 'text' => "  a\x00b\x07c\r\nd\te\x7F \n"]))[0];

        $this->assertSame("abc\nde", $item['text']);
    }

    public function test_text_is_cut_to_the_limit_by_characters(): void
    {
        config(['filament-image-annotations.limits.text' => 4]);

        $item = $this->items($this->rect(['type' => 'text', 'pts' => [[0.3, 0.3]], 'text' => 'Привіт світе']))[0];

        $this->assertSame('Прив', $item['text']);
    }

    public function test_coordinates_are_clamped_and_rounded(): void
    {
        $item = $this->items($this->rect(['pts' => [[-3, '0.123456'], [7.5, 1]]]))[0];

        $this->assertSame([[0.0, 0.1235], [1.0, 1.0]], $item['pts']);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badPoints(): iterable
    {
        yield 'letters' => [[['a', 'b'], [0, 0]]];
        yield 'bool' => [[[true, false], [0, 0]]];
        yield 'null' => [[[null, 0], [0, 0]]];
        yield 'nested array' => [[[[0], 0], [0, 0]]];
        yield 'one number' => [[[0.5], [0, 0]]];
        yield 'point not array' => [[0.5, [0, 0]]];
        yield 'infinite' => [[['INF', 0], [0, 0]]];
        yield 'pts not array' => ['0,0 1,1'];
    }

    #[DataProvider('badPoints')]
    public function test_a_non_numeric_point_drops_the_item(mixed $pts): void
    {
        $this->assertSame([], $this->items($this->rect(['pts' => $pts])));
    }

    public function test_colour_accepts_palette_names_and_hex_case_insensitively(): void
    {
        $this->assertSame('#3b82f6', $this->items($this->rect(['color' => 'Blue']))[0]['color']);
        $this->assertSame('#abcdef', $this->items($this->rect(['color' => '#ABCDEF']))[0]['color']);
    }

    public function test_an_unusable_colour_falls_back_to_the_first_palette_colour(): void
    {
        foreach (['nope', '#fff', '#12345g', 'javascript:alert(1)', ['red'], null, 12, '#ff0000; x', ''] as $bad) {
            $this->assertSame('#ef4444', $this->items($this->rect(['color' => $bad]))[0]['color'], json_encode($bad));
        }
    }

    public function test_width_is_clamped_with_a_default(): void
    {
        $this->assertSame(1, $this->items($this->rect(['width' => -5]))[0]['width']);
        $this->assertSame(24, $this->items($this->rect(['width' => 999]))[0]['width']);
        $this->assertSame(6, $this->items($this->rect(['width' => '5.6']))[0]['width']);
        $this->assertSame(4, $this->items($this->rect(['width' => 'wide']))[0]['width']);
        $this->assertSame(4, $this->items($this->rect(['width' => [8]]))[0]['width']);
        $this->assertSame(4, $this->items(array_diff_key($this->rect(), ['width' => 1]))[0]['width']);
    }

    public function test_ids_are_checked_and_made_unique(): void
    {
        $items = $this->items(
            $this->rect(['id' => 'ok-1_x']),
            $this->rect(['id' => 'ok-1_x']),
            $this->rect(['id' => 'bad id!']),
            $this->rect(['id' => ['x']]),
            $this->rect(['id' => str_repeat('a', 41)]),
            array_diff_key($this->rect(), ['id' => 1]),
        );

        $ids = array_column($items, 'id');

        $this->assertCount(6, $ids);
        $this->assertSame('ok-1_x', $ids[0]);
        $this->assertCount(6, array_unique($ids));

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,40}$/', $id);
        }
    }

    public function test_unknown_keys_are_dropped(): void
    {
        $item = $this->items($this->rect(['onclick' => 'x()', 'style' => 'color:red']))[0];

        $this->assertSame(['id', 'type', 'pts', 'color', 'width'], array_keys($item));
    }

    public function test_the_item_count_is_limited(): void
    {
        config(['filament-image-annotations.limits.shapes' => 3]);

        $items = $this->items(...array_fill(0, 10, $this->rect(['id' => 'x'])));

        $this->assertCount(3, $items);
    }
}
