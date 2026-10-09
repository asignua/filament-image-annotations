<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * The media table and the annotations' published-migration stub, so `testbench serve` and the tests get the same schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            __DIR__.'/../../../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub',
            __DIR__.'/../../../database/migrations/create_image_annotations_table.php.stub',
        ] as $stub) {
            (include $stub)->up();
        }
    }
};
