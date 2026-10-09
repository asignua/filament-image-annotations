<?php

declare(strict_types=1);

/*
 * Filament Image Annotations. Annotations are a JSON layer stored in their own table; the original image
 * is never modified.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */
    'table' => 'image_annotations',

    /*
    |--------------------------------------------------------------------------
    | Palette
    |--------------------------------------------------------------------------
    |
    | Ordered name => hex. The first colour is the fallback for an unknown value.
    */
    'palette' => [
        'red' => '#ef4444',
        'orange' => '#f97316',
        'yellow' => '#eab308',
        'green' => '#22c55e',
        'blue' => '#3b82f6',
        'purple' => '#a855f7',
        'black' => '#111827',
        'white' => '#ffffff',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stroke widths
    |--------------------------------------------------------------------------
    |
    | Whole numbers stored as given; the renderer scales them to the displayed image.
    | The middle one is the default for a shape that arrives without a width.
    */
    'widths' => [2, 4, 8],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    |
    | shapes: items per image; points: points of one pen/highlight stroke; text: characters of a text item.
    */
    'limits' => [
        'shapes' => 200,
        'points' => 2000,
        'text' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | null = the model of the default auth provider.
    */
    'user_model' => null,

];
