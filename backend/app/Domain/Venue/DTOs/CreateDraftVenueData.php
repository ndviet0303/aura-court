<?php

namespace App\Domain\Venue\DTOs;

use App\Http\Requests\Venue\SaveVenueRequest;

readonly class CreateDraftVenueData
{
    public function __construct(
        public int $organizationId,
        public int $actorId,
        public string $name,
        public string $address,
        public ?string $city = null,
        public ?string $district = null,
        public ?string $ward = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $description = null,
        public ?string $googleMapsUrl = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public array $amenities = [],
        public array $images = [],
    ) {}

    public static function fromRequest(SaveVenueRequest $request, int $organizationId): self
    {
        return new self(
            organizationId: $organizationId,
            actorId: (int) $request->user()->id,
            name: (string) $request->validated('name'),
            address: (string) $request->validated('address'),
            city: $request->validated('city'),
            district: $request->validated('district'),
            ward: $request->validated('ward'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            description: $request->validated('description'),
            googleMapsUrl: $request->validated('google_maps_url'),
            latitude: $request->filled('latitude') ? (float) $request->validated('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->validated('longitude') : null,
            amenities: $request->validated('amenities') ?? [],
            images: $request->validated('images') ?? [],
        );
    }
}
