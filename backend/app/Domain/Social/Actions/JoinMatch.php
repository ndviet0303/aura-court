<?php

namespace App\Domain\Social\Actions;

use App\Models\MatchParticipant;
use App\Models\MatchPost;
use App\Services\Social\MatchService;

class JoinMatch
{
    public function __construct(
        private readonly MatchService $matches,
    ) {}

    public function execute(MatchPost $post, int $userId): MatchParticipant
    {
        return $this->matches->joinPost($post, $userId);
    }
}
