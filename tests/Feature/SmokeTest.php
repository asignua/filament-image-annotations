<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\ImageAnnotationsPlugin;
use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Filament\Facades\Filament;

class SmokeTest extends TestCase
{
    public function test_the_panel_boots(): void
    {
        $this->assertSame('admin', Filament::getCurrentPanel()?->getId());
    }

    public function test_the_plugin_is_registered_on_the_panel(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPlugin('asignua-filament-image-annotations'));
        $this->assertInstanceOf(ImageAnnotationsPlugin::class, $panel->getPlugin('asignua-filament-image-annotations'));
    }

    public function test_the_translations_are_loaded(): void
    {
        $this->assertSame('Sample', __('filament-image-annotations::filament-image-annotations.sample'));
    }
}
