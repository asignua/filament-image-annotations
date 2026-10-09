<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Support;

use Illuminate\Support\Str;

/**
 * Turns whatever the browser sent into the stored form {"v":1,"items":[…]}. Never throws: what cannot be
 * fixed is dropped. Text is NOT HTML-escaped here — the renderer must use textContent.
 */
class ShapeSanitizer
{
    public const int VERSION = 1;

    private const array TWO_POINT_TYPES = ['arrow', 'line', 'rect', 'ellipse'];

    public const int MAX_PIN = 999;

    private const array STROKE_TYPES = ['pen', 'highlight'];

    /**
     * @return array{v: int, items: list<array<string, mixed>>}
     */
    public static function sanitize(mixed $input): array
    {
        if (is_string($input)) {
            $input = json_decode($input, true);
        }

        $result = ['v' => self::VERSION, 'items' => []];

        if (!is_array($input) || !isset($input['items']) || !is_array($input['items'])) {
            return $result;
        }

        $max = max(0, (int) config('filament-image-annotations.limits.shapes', 200));
        $seen = [];

        foreach ($input['items'] as $raw) {
            if (count($result['items']) >= $max) {
                break;
            }

            $item = self::item($raw, $seen);

            if ($item !== null) {
                $result['items'][] = $item;
            }
        }

        $result['items'] = self::renumberPins($result['items']);

        return $result;
    }

    /**
     * A pin number is unique within the layer: the first pin keeps its number, a later duplicate gets max + 1
     * (and is dropped when that would pass the bound).
     *
     * @param list<array<string, mixed>> $items
     *
     * @return list<array<string, mixed>>
     */
    private static function renumberPins(array $items): array
    {
        $max = 0;

        foreach ($items as $item) {
            if ($item['type'] === 'pin') {
                $max = max($max, (int) $item['n']);
            }
        }

        $used = [];
        $result = [];

        foreach ($items as $item) {
            if ($item['type'] === 'pin') {
                $n = (int) $item['n'];

                if (isset($used[$n])) {
                    if ($max >= self::MAX_PIN) {
                        continue;
                    }

                    $n = ++$max;
                    $item['n'] = $n;
                }

                $used[$n] = true;
            }

            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<string, true> $seen
     *
     * @return array<string, mixed>|null
     */
    private static function item(mixed $raw, array &$seen): ?array
    {
        if (!is_array($raw) || !isset($raw['type']) || !is_string($raw['type'])) {
            return null;
        }

        $type = $raw['type'];
        $pts = self::points($raw['pts'] ?? null, $type);

        if ($pts === null) {
            return null;
        }

        $item = [
            'id' => self::id($raw['id'] ?? null, $seen),
            'type' => $type,
            'pts' => $pts,
            'color' => self::color($raw['color'] ?? null),
            'width' => self::width($raw['width'] ?? null),
        ];

        if ($type === 'text') {
            $text = self::text($raw['text'] ?? null);

            if ($text === '') {
                return null;
            }

            $item['text'] = $text;
        }

        if ($type === 'pin') {
            $n = self::pinNumber($raw['n'] ?? null);

            // A pin without a usable number has nothing to anchor a thread to.
            if ($n === null) {
                return null;
            }

            $item['n'] = $n;
        }

        return $item;
    }

    private static function pinNumber(mixed $value): ?int
    {
        if (is_string($value) && preg_match('/^[0-9]{1,4}$/', $value) === 1) {
            $value = (int) $value;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        return is_int($value) && $value >= 1 && $value <= self::MAX_PIN ? $value : null;
    }

    /**
     * @return list<array{float, float}>|null
     */
    private static function points(mixed $raw, string $type): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $raw = array_values($raw);
        $count = count($raw);

        $valid = match (true) {
            in_array($type, self::TWO_POINT_TYPES, true) => $count === 2,
            in_array($type, self::STROKE_TYPES, true) => $count >= 2 && $count <= max(2, (int) config('filament-image-annotations.limits.points', 2000)),
            in_array($type, ['text', 'pin'], true) => $count === 1,
            default => false,
        };

        if (!$valid) {
            return null;
        }

        $pts = [];

        foreach ($raw as $point) {
            if (!is_array($point)) {
                return null;
            }

            $point = array_values($point);

            if (count($point) < 2) {
                return null;
            }

            $x = self::coordinate($point[0]);
            $y = self::coordinate($point[1]);

            if ($x === null || $y === null) {
                return null;
            }

            $pts[] = [$x, $y];
        }

        return $pts;
    }

    private static function coordinate(mixed $value): ?float
    {
        if (is_bool($value) || !is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return null;
        }

        return round(min(1.0, max(0.0, $number)), 4);
    }

    private static function color(mixed $value): string
    {
        /** @var array<string, string> $palette */
        $palette = (array) config('filament-image-annotations.palette', []);

        if (is_string($value)) {
            $lower = strtolower(trim($value));

            if (isset($palette[$lower])) {
                return strtolower($palette[$lower]);
            }

            if (preg_match('/^#[0-9a-f]{6}$/', $lower) === 1) {
                return $lower;
            }
        }

        $first = reset($palette);

        return $first === false ? '#ef4444' : strtolower($first);
    }

    private static function width(mixed $value): int
    {
        if (is_bool($value) || !is_numeric($value)) {
            /** @var list<int> $widths */
            $widths = array_values((array) config('filament-image-annotations.widths', [2, 4, 8]));

            return $widths[1] ?? $widths[0] ?? 4;
        }

        return min(24, max(1, (int) round((float) $value)));
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Control characters except the line feed (tab included: it has no meaning on an image).
        $clean = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', '', $value) ?? '';

        return mb_substr(trim($clean), 0, max(1, (int) config('filament-image-annotations.limits.text', 500)));
    }

    /**
     * @param array<string, true> $seen
     */
    private static function id(mixed $value, array &$seen): string
    {
        $id = is_string($value) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $value) === 1 ? $value : null;

        while ($id === null || isset($seen[$id])) {
            $id = Str::random(10);
        }

        $seen[$id] = true;

        return $id;
    }
}
