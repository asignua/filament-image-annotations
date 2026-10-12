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

        $post = Post::query()->create(['user_id' => $alice->getKey(), 'title' => 'Loading dock, north wall']);
        $media = $post->addMedia($this->picture())->usingName('Loading dock')->usingFileName('loading-dock.png')->toMediaCollection('photos');

        foreach ([['Office entrance', [214, 200, 188]], ['Warehouse B, south side', [190, 204, 196]]] as [$title, $wall]) {
            $other = Post::query()->create(['user_id' => $alice->getKey(), 'title' => $title]);
            $other->addMedia($this->picture($wall))->usingName($title)->usingFileName(str($title)->slug().'.png')->toMediaCollection('photos');
        }

        ImageAnnotations::store()->save(new MediaImageSource($media), $alice, [
            'v' => 1,
            'items' => [
                ['id' => 'demo-door', 'type' => 'rect', 'pts' => [[0.34, 0.40], [0.66, 0.77]], 'color' => 'blue', 'width' => 4],
                ['id' => 'demo-arrow', 'type' => 'arrow', 'pts' => [[0.78, 0.9], [0.757, 0.66]], 'color' => 'red', 'width' => 8],
                ['id' => 'demo-text', 'type' => 'text', 'pts' => [[0.4, 0.9]], 'color' => 'white', 'width' => 8, 'text' => 'Move the handle to the left'],
                ['id' => 'demo-crates', 'type' => 'ellipse', 'pts' => [[0.06, 0.52], [0.2, 0.79]], 'color' => 'orange', 'width' => 4],
                ['id' => 'demo-note', 'type' => 'text', 'pts' => [[0.03, 0.84]], 'color' => 'yellow', 'width' => 8, 'text' => 'Crates block the exit'],
            ],
        ]);
    }

    /**
     * A 1600x1000 warehouse front drawn with GD (no binary asset in the repository): sky, wall, a roller door, windows, crates.
     *
     * @param array{0:int,1:int,2:int} $wall
     */
    private function picture(array $wall = [201, 205, 214]): string
    {
        $w = 1600;
        $h = 1000;
        $im = imagecreatetruecolor($w, $h);
        imageantialias($im, true);
        $c = fn (int $r, int $g, int $b): int => (int) imagecolorallocate($im, $r, $g, $b);

        for ($y = 0; $y < 770; $y++) {
            imageline($im, 0, $y, $w, $y, $c(120 + (int) ($y / 9), 170 + (int) ($y / 14), 235 - (int) ($y / 16)));
        }
        imagefilledellipse($im, 1380, 150, 130, 130, $c(255, 244, 200));
        imagefilledellipse($im, 330, 120, 260, 70, $c(250, 252, 255));
        imagefilledellipse($im, 430, 100, 200, 80, $c(250, 252, 255));
        // ground
        imagefilledrectangle($im, 0, 760, $w, $h, $c(96, 100, 108));

        for ($x = 0; $x < $w; $x += 220) {
            imagefilledrectangle($im, $x, 930, $x + 110, 940, $c(235, 200, 70));
        }
        // wall + roof
        imagefilledrectangle($im, 80, 300, 1520, 760, $c(...$wall));
        imagefilledrectangle($im, 60, 270, 1540, 310, $c(70, 78, 92));

        for ($x = 80; $x < 1520; $x += 160) {
            imageline($im, $x, 310, $x, 760, $c($wall[0] - 22, $wall[1] - 22, $wall[2] - 22));
        }
        // a roller door
        imagefilledrectangle($im, 560, 420, 1040, 760, $c(52, 58, 70));
        imagefilledrectangle($im, 572, 432, 1028, 760, $c(222, 226, 232));

        for ($y = 450; $y < 760; $y += 28) {
            imagefilledrectangle($im, 572, $y, 1028, $y + 3, $c(170, 176, 188));
        }
        imagefilledrectangle($im, 780, 740, 820, 752, $c(60, 64, 72));
        // a side door with a handle
        imagefilledrectangle($im, 1190, 470, 1330, 760, $c(48, 54, 66));
        imagefilledrectangle($im, 1200, 480, 1320, 760, $c(37, 99, 160));
        imagefilledrectangle($im, 1210, 600, 1224, 650, $c(235, 235, 240));

        // windows
        foreach ([[170, 420], [330, 420], [1380, 420]] as [$x, $y]) {
            imagefilledrectangle($im, $x, $y, $x + 120, $y + 150, $c(60, 66, 80));
            imagefilledrectangle($im, $x + 8, $y + 8, $x + 112, $y + 142, $c(150, 196, 232));
            imageline($im, $x + 60, $y + 8, $x + 60, $y + 142, $c(60, 66, 80));
        }
        // sign
        imagefilledrectangle($im, 600, 330, 1000, 390, $c(250, 204, 21));

        // crates and pallets
        foreach ([[120, 640, 280, 760, [180, 130, 70]], [150, 540, 270, 640, [200, 150, 85]], [1090, 660, 1170, 760, [180, 130, 70]]] as [$x1, $y1, $x2, $y2, $rgb]) {
            imagefilledrectangle($im, $x1, $y1, $x2, $y2, $c(...$rgb));
            imagerectangle($im, $x1, $y1, $x2, $y2, $c(110, 76, 38));
            imageline($im, $x1, $y1, $x2, $y2, $c(110, 76, 38));
        }

        $path = sys_get_temp_dir().'/image-annotations-demo-'.uniqid().'.png';
        imagepng($im, $path);

        return $path;
    }
}
