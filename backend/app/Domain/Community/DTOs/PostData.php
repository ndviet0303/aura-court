<?php

namespace App\Domain\Community\DTOs;

use App\Http\Requests\Community\SavePostRequest;
use App\Models\FeedPost;
use Illuminate\Support\Carbon;

/** FR-113: a share carries text and images only; a matchmaking post adds where and when to play (BR-146). */
readonly class PostData
{
    /** @param  list<string>  $images */
    public function __construct(
        public string $type,
        public string $content,
        public array $images,
        public ?string $sport = null,
        public ?int $venueId = null,
        public ?string $address = null,
        public ?string $courtCode = null,
        public ?Carbon $playAt = null,
    ) {}

    public static function from(SavePostRequest $request): self
    {
        $type = (string) $request->validated('type');
        $content = trim((string) $request->validated('content'));
        $images = array_values((array) $request->validated('images', []));
        if ($type !== FeedPost::TYPE_MATCHMAKING) {
            return new self($type, $content, $images);
        }
        $venueId = $request->validated('venue_id');
        $playAt = $request->validated('play_at');

        return new self(
            type: $type,
            content: $content,
            images: $images,
            sport: $request->validated('sport'),
            venueId: $venueId === null ? null : (int) $venueId,
            address: $request->validated('address'),
            courtCode: $request->validated('court_code'),
            playAt: $playAt === null ? null : Carbon::parse($playAt)->setTimezone(config('app.timezone')),
        );
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return [
            'type' => $this->type,
            'content' => $this->content,
            'images' => $this->images,
            'sport' => $this->sport,
            'venue_id' => $this->venueId,
            'address' => $this->address,
            'court_code' => $this->courtCode,
            'play_at' => $this->playAt,
        ];
    }
}
