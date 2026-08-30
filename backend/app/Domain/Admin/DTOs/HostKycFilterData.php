<?php

namespace App\Domain\Admin\DTOs;

use Illuminate\Http\Request;

readonly class HostKycFilterData
{
    public function __construct(
        public ?string $status = null,
        public ?string $search = null,
        public int $perPage = 30,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            status: $request->query('status'),
            search: $request->query('search'),
            perPage: (int) $request->query('per_page', 30),
        );
    }
}
