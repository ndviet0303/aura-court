<?php

namespace App\Contracts\Repositories;

use App\Models\Booking;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function findById(int $id): ?Booking;

    public function findByCode(string $code): ?Booking;

    public function create(array $attributes): Booking;

    public function createSlots(Booking $booking, array $slots): void;

    public function updateStatus(Booking $booking, string $status): bool;

    public function paginateUserBookings(int $userId, int $perPage = 20): LengthAwarePaginator;

    public function paginateAdminBookings(array $filters = [], int $perPage = 30): LengthAwarePaginator;
}
