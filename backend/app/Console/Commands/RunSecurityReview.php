<?php

namespace App\Console\Commands;

use App\Services\SecurityReviewService;
use Illuminate\Console\Command;

class RunSecurityReview extends Command
{
    protected $signature = 'hardening:security-review';
    protected $description = 'Review secret handling, RLS, exports, elevated access, rate limits, and audit paths';
    public function handle(SecurityReviewService $service): int { $result = $service->run(); $this->line(json_encode($result, JSON_PRETTY_PRINT)); return in_array('missing', $result, true) ? self::FAILURE : self::SUCCESS; }
}
