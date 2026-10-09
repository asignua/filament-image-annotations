<?php

declare(strict_types=1);

namespace Workbench\App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Workbench\App\Models\Post;

class PostPolicy
{
    public function view(Authenticatable $user, Post $post): bool
    {
        return $post->user_id === $user->getAuthIdentifier();
    }

    public function update(Authenticatable $user, Post $post): bool
    {
        return $post->user_id === $user->getAuthIdentifier();
    }
}
