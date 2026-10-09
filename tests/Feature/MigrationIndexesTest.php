<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL / MariaDB cap an index at 3072 bytes, SQLite does not — so an index over the 512-character `key` next to the
 * morph columns passed every test here and broke `migrate` on a real host (error 1071).
 */
class MigrationIndexesTest extends TestCase
{
    public function test_no_index_covers_the_long_key_column(): void
    {
        $table = (string) config('filament-image-annotations.table', 'image_annotations');
        $indexes = Schema::getIndexes($table);

        $this->assertNotEmpty($indexes);

        foreach ($indexes as $index) {
            $this->assertNotContains('key', $index['columns'], 'Index '.$index['name'].' covers `key` and is too long for MySQL');
        }
    }

    public function test_lookups_by_record_still_have_an_index(): void
    {
        $table = (string) config('filament-image-annotations.table', 'image_annotations');
        $columns = array_map(fn (array $index): array => $index['columns'], Schema::getIndexes($table));

        $this->assertContains(['annotatable_type', 'annotatable_id'], $columns);
    }
}
