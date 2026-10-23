<?php

namespace App\Http\Resources\Community;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BR-144: a post, comment or review shows only its author's public name and avatar.
 *
 * @mixin User
 */
class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'avatar_url' => $this->avatar_url];
    }
}
