<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Sources;

use Asignua\FilamentImageAnnotations\Contracts\ImageSource;
use Asignua\FilamentImageAnnotations\ImageAnnotations;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

abstract class AbstractImageSource implements ImageSource
{
    /**
     * The model whose policy decides (for Media: the model the file belongs to).
     */
    abstract protected function owner(): ?Model;

    public function canView(?Authenticatable $user): bool
    {
        return $this->authorize($user, 'view', 'view');
    }

    public function canAnnotate(?Authenticatable $user): bool
    {
        return $this->authorize($user, 'annotate', 'update');
    }

    private function authorize(?Authenticatable $user, string $ability, string $policyAbility): bool
    {
        $callback = ImageAnnotations::authorizer();

        if ($callback !== null) {
            return (bool) $callback($user, $this, $ability);
        }

        if ($user === null) {
            return false;
        }

        $owner = $this->owner();

        if ($owner === null || Gate::getPolicyFor($owner) === null) {
            return true;
        }

        return Gate::forUser($user)->allows($policyAbility, $owner);
    }
}
