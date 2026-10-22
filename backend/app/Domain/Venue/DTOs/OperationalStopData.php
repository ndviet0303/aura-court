<?php

namespace App\Domain\Venue\DTOs;

use App\Http\Requests\Venue\PauseRequest;
use Carbon\CarbonImmutable;

/** A pause or closure of a venue or court (D5). Times are UTC; a start in the allowed clock skew becomes now. */
readonly class OperationalStopData
{
    public function __construct(
        public int $actorId,
        public string $kind,
        public string $reason,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $expectedEndAt = null,
    ) {}

    public static function fromRequest(PauseRequest $request): self
    {
        $now = CarbonImmutable::now('UTC');
        $startsAt = $request->filled('starts_at') ? CarbonImmutable::parse($request->validated('starts_at'))->utc() : $now;
        $expectedEndAt = $request->validated('expected_end_at');

        return new self(
            actorId: (int) $request->user()->id,
            kind: $request->stopKind(),
            reason: (string) $request->validated('reason'),
            startsAt: $startsAt->max($now),
            expectedEndAt: $expectedEndAt === null ? null : CarbonImmutable::parse($expectedEndAt)->utc(),
        );
    }

    /** A stop that has already started changes the status in its own transaction; a later one waits for D6. */
    public function startsNow(): bool
    {
        return $this->startsAt->lte(CarbonImmutable::now());
    }

    /** @return array<string, mixed> the stop row of a whole venue ($courtId null) or of one court */
    public function stopAttributes(int $venueId, ?int $courtId): array
    {
        return [
            'venue_id' => $venueId,
            'court_id' => $courtId,
            'kind' => $this->kind,
            'reason' => $this->reason,
            'starts_at' => $this->startsAt,
            'expected_end_at' => $this->expectedEndAt,
            'created_by' => $this->actorId,
        ];
    }
}
