<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Domain\Venue\Actions\SaveVenueBookingPolicy;
use App\Domain\Venue\Actions\SaveVenueOperatingSchedule;
use App\Domain\Venue\Actions\SaveVenueScheduleException;
use App\Http\Controllers\Concerns\AuthorizesVenueRead;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\DeleteVenueScheduleExceptionRequest;
use App\Http\Requests\Partner\SaveVenueOperatingScheduleRequest;
use App\Http\Requests\Partner\SaveVenueScheduleExceptionRequest;
use App\Http\Requests\Partner\StoreVenueBookingPolicyRequest;
use App\Models\OrganizationMember;
use App\Models\Venue;
use App\Models\VenueOperatingSchedule;
use App\Models\VenueScheduleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerVenueScheduleController extends Controller
{
    use AuthorizesVenueRead;

    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
    ) {}

    public function bookingPolicy(Request $request, int $organizationId, int $venueId): JsonResponse
    {
        $venue = $this->authorizeVenue($request, $organizationId, $venueId, 'Bạn không có quyền xem chính sách đặt sân này.');

        return response()->json([
            'data' => $venue->currentBookingPolicy,
        ]);
    }

    public function saveBookingPolicy(
        StoreVenueBookingPolicyRequest $request,
        int $organizationId,
        int $venueId,
        SaveVenueBookingPolicy $action
    ): JsonResponse {
        $policy = $action->execute($venueId, (int) $request->user()->id, $request->validated('settings'));

        return response()->json(['data' => $policy], 201);
    }

    /** The owner and assigned staff read; only the owner saves (BR-31). */
    public function operatingSchedule(Request $request, int $organizationId, int $venueId): JsonResponse
    {
        $venue = $this->authorizeVenueRead($request, $organizationId, $venueId);
        $courtId = $request->query('court_id');
        if ($courtId !== null) {
            abort_unless($venue->courts()->whereKey($courtId)->exists(), 404, 'Sân không thuộc cơ sở này.');
        }
        $scopeKey = $courtId === null ? 'venue' : 'court:'.$courtId;
        $schedule = VenueOperatingSchedule::query()
            ->where('venue_id', $venue->id)
            ->where('scope_key', $scopeKey)
            ->first();
        $inherited = false;
        if ($courtId !== null && ! $schedule) {
            $schedule = VenueOperatingSchedule::query()
                ->where('venue_id', $venue->id)
                ->where('scope_key', 'venue')
                ->first();
            $inherited = $schedule !== null;
        }

        return response()->json(['data' => $schedule, 'inherited' => $inherited]);
    }

    public function saveOperatingSchedule(
        SaveVenueOperatingScheduleRequest $request,
        int $organizationId,
        int $venueId,
        SaveVenueOperatingSchedule $action
    ): JsonResponse {
        $schedule = $action->execute($venueId, (int) $request->user()->id, $request->validated());

        return response()->json(['data' => $schedule]);
    }

    public function scheduleExceptions(Request $request, int $organizationId, int $venueId): JsonResponse
    {
        $venue = $this->authorizeVenueRead($request, $organizationId, $venueId);
        $courtId = $request->query('court_id');
        $query = VenueScheduleException::query()->where('venue_id', $venue->id);
        if ($courtId !== null) {
            abort_unless($venue->courts()->whereKey($courtId)->exists(), 404, 'Sân không thuộc cơ sở này.');
            $query->whereIn('scope_key', ['venue', 'court:'.$courtId]);
        } else {
            $query->where('scope_key', 'venue');
        }

        return response()->json(['data' => $query->orderBy('exception_date')->get()]);
    }

    public function saveScheduleException(
        SaveVenueScheduleExceptionRequest $request,
        int $organizationId,
        int $venueId,
        SaveVenueScheduleException $action
    ): JsonResponse {
        $exception = $action->execute($venueId, (int) $request->user()->id, $request->validated());

        return response()->json(['data' => $exception], 201);
    }

    /** D7: deleting an exception that shortens service time needs the acknowledgement and opens cases. */
    public function deleteScheduleException(DeleteVenueScheduleExceptionRequest $request, int $organizationId, int $venueId, int $exceptionId, SaveVenueScheduleException $action): JsonResponse
    {
        $action->delete($venueId, $exceptionId, $request->boolean('acknowledge_affected'));

        return response()->json(['message' => 'Đã xóa ngoại lệ lịch hoạt động.']);
    }

    private function authorizeVenue(Request $request, int $organizationId, int $venueId, string $message): Venue
    {
        $venue = $this->venueRepo->findById($venueId);
        abort_if($venue === null || (int) $venue->organization_id !== $organizationId, 404, 'Cơ sở thể thao không tồn tại.');
        $user = $request->user();
        abort_unless($user && ((
            $user->canManageVenue($venue)
            && $user->hasRoleInOrganization($organizationId, [
                OrganizationMember::ROLE_ORGANIZATION_OWNER,
            ])
        )), 403, $message);

        return $venue;
    }
}
