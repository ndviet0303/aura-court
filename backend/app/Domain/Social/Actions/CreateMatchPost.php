<?php

namespace App\Domain\Social\Actions;

use App\Models\MatchPost;
use App\Services\Social\MatchService;

class CreateMatchPost
{
    public function __construct(
        private readonly MatchService $matches,
    ) {}

    public function execute(int $userId, int $organizationId, array $data): MatchPost
    {
        return $this->matches->createPost($userId, $organizationId, $data);
    }
}
