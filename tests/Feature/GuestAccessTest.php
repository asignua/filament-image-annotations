<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Tests\Feature;

use Asignua\FilamentImageAnnotations\Tests\TestCase;
use Workbench\App\Filament\Resources\Posts\PostResource;
use Workbench\App\Models\Post;

class GuestAccessTest extends TestCase
{
    public function test_a_guest_is_sent_to_the_panel_login(): void
    {
        $post = Post::create([]);

        $this->get(PostResource::getUrl('view', ['record' => $post]))
            ->assertRedirect(route('filament.admin.auth.login'));
    }
}
