<?php

namespace App\Http\Controllers\Customer;

use App\Contracts\Repositories\VenueCustomerRepositoryInterface;
use App\Domain\Attendance\Rules\AttendanceGuard;
use App\Http\Controllers\Concerns\ResolvesOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\VenueCustomerRequest;
use App\Http\Resources\Attendance\OperationalReservationResource;
use App\Http\Resources\Customer\VenueCustomerResource;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\JsonResponse;

/** FR-110, BR-140: customers of a venue for its owner and the staff assigned to it (UC-7.1, UC-7.2). */
class VenueCustomerController extends Controller
{
    use ResolvesOrganization;

    public function __construct(private readonly VenueCustomerRepositoryInterface $customers) {}

    public function index(VenueCustomerRequest $request, Venue $venue): JsonResponse
    {
        AttendanceGuard::venue($venue, (int) $this->organization($request)->id, $request->user());
        $quarter = $request->quarter();
        $page = $this->customers->paginate($venue->id, $quarter, $request->validated('search'), (int) ($request->validated('per_page') ?? 20));

        return response()->json([
            'quarter' => $quarter->label,
            'data' => VenueCustomerResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function show(VenueCustomerRequest $request, Venue $venue, User $user): JsonResponse
    {
        AttendanceGuard::venue($venue, (int) $this->organization($request)->id, $request->user());
        $quarter = $request->quarter();
        $customer = $this->customers->find($venue->id, $user->id, $quarter)
            ?? AttendanceGuard::fail('CUSTOMER_NOT_FOUND', 'Khách hàng chưa đặt sân tại cơ sở này.', 404);

        return response()->json([
            'quarter' => $quarter->label,
            'customer' => VenueCustomerResource::make($customer),
            'history' => OperationalReservationResource::collection($this->customers->history($venue->id, $user->id, 50)),
        ]);
    }
}
