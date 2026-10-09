<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Asignua\FilamentImageAnnotations\Sources\MediaImageSource;
use Illuminate\Database\Seeder;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * For trying the annotations by hand (`vendor/bin/testbench workbench:build`, then `testbench serve`): the user
 * alice@example.com (password "password") owns a post with a generated picture and a few annotations;
 * bob@example.com may not touch it.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alice = User::query()->create(['name' => 'Alice', 'email' => 'alice@example.com', 'password' => 'password']);
        User::query()->create(['name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'password']);

        $post = Post::query()->create(['user_id' => $alice->getKey()]);
        $media = $post->addMedia($this->picture())->usingFileName('demo.png')->toMediaCollection('photos');

        ImageAnnotations::store()->save(new MediaImageSource($media), $alice, [
            'v' => 1,
            'items' => [
                ['id' => 'demo-arrow', 'type' => 'arrow', 'pts' => [[0.12, 0.2], [0.38, 0.45]], 'color' => 'red', 'width' => 8],
                ['id' => 'demo-rect', 'type' => 'rect', 'pts' => [[0.55, 0.18], [0.85, 0.5]], 'color' => 'blue', 'width' => 4],
                ['id' => 'demo-text', 'type' => 'text', 'pts' => [[0.1, 0.62]], 'color' => 'white', 'width' => 8, 'text' => "Перевірте це місце\nCheck this spot"],
            ],
        ]);
    }

    /**
     * A 1200x800 gradient with a few blocks, drawn with GD (no binary asset in the repository).
     */
    private function picture(): string
    {
        $width = 1200;
        $height = 800;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $shade = (int) (60 + 120 * $y / $height);
            imageline($image, 0, $y, $width, $y, (int) imagecolorallocate($image, $shade, $shade + 20, 200 - (int) ($y / 8)));
        }

        foreach ([[100, 380, 420, 700, [230, 230, 230]], [520, 300, 760, 700, [90, 90, 110]], [820, 450, 1100, 700, [245, 210, 120]]] as [$x1, $y1, $x2, $y2, $rgb]) {
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, (int) imagecolorallocate($image, ...$rgb));
        }

        $path = sys_get_temp_dir().'/image-annotations-demo-'.uniqid().'.png';
        imagepng($image, $path);

        return $path;
    }
}
