<?php

namespace App\Http\Controllers\Partner;

use App\Contracts\Repositories\VenueLifecycleRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\RotateReceivingConnectionApiTokenRequest;
use App\Http\Requests\Partner\RotateReceivingConnectionCredentialRequest;
use App\Http\Requests\Partner\StoreReceivingConnectionRequest;
use App\Http\Resources\Partner\ReceivingConnectionResource;
use App\Models\OrganizationMember;
use App\Models\ReceivingConnection;
use App\Models\Venue;
use App\Services\Payment\SePayReceivingConnectionService;
use App\Services\Payment\SePayTransactionQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceivingConnectionController extends Controller
{
    public function __construct(
        private readonly VenueLifecycleRepositoryInterface $venueRepo,
    ) {}

    public function show(Request $request, int $organizationId, int $venueId): JsonResponse
    {
        $venue = $this->authorizeVenue($request, $organizationId, $venueId);
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venue->id)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $connection ? ReceivingConnectionResource::make($connection) : null,
        ]);
    }

    public function store(
        StoreReceivingConnectionRequest $request,
        int $organizationId,
        int $venueId,
        SePayReceivingConnectionService $connections
    ): JsonResponse {
        $venue = $this->findScopedVenue($organizationId, $venueId);
        $connection = $connections->create($venue, $request->user(), $request->validated());

        return response()->json([
            'data' => ReceivingConnectionResource::make($connection),
            'message' => 'Receiving connection created. Configure the SePay webhook with Api_Key authentication and save the same key here to continue verification.',
        ], 201);
    }

    public function rotateCredential(
        RotateReceivingConnectionCredentialRequest $request,
        int $organizationId,
        int $venueId,
        int $connectionId,
        SePayReceivingConnectionService $connections
    ): JsonResponse {
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venueId)
            ->findOrFail($connectionId);

        $connection = $connections->rotateCredential(
            $connection,
            $request->user(),
            $request->validated('webhook_api_key')
        );

        return response()->json([
            'data' => ReceivingConnectionResource::make($connection),
            'message' => 'Credential saved. A new authenticated inbound event is required to verify this connection.',
        ]);
    }

    public function evidence(Request $request, int $organizationId, int $venueId, int $connectionId): JsonResponse
    {
        $this->authorizeVenue($request, $organizationId, $venueId);
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venueId)
            ->findOrFail($connectionId);

        return response()->json([
            'data' => $connection->evidence()->get()->map(fn ($event) => [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'credential_version' => (int) $event->credential_version,
                'event_fingerprint' => $event->event_fingerprint,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function rotateApiToken(
        RotateReceivingConnectionApiTokenRequest $request,
        int $organizationId,
        int $venueId,
        int $connectionId,
        SePayReceivingConnectionService $connections
    ): JsonResponse {
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venueId)
            ->findOrFail($connectionId);

        $connection = $connections->rotateApiToken(
            $connection,
            $request->user(),
            $request->validated('api_token'),
            $request->validated('provider_account_id')
        );

        return response()->json([
            'data' => ReceivingConnectionResource::make($connection),
            'message' => 'SePay API credential saved securely.',
        ]);
    }

    public function syncTransactions(
        Request $request,
        int $organizationId,
        int $venueId,
        int $connectionId,
        SePayTransactionQueryService $transactions
    ): JsonResponse {
        $this->authorizeVenue($request, $organizationId, $venueId);
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venueId)
            ->findOrFail($connectionId);

        return response()->json(['data' => $transactions->sync($connection)]);
    }

    public function select(
        Request $request,
        int $organizationId,
        int $venueId,
        int $connectionId
    ): JsonResponse {
        $venue = $this->authorizeVenue($request, $organizationId, $venueId);
        $connection = ReceivingConnection::query()
            ->where('organization_id', $organizationId)
            ->where('venue_id', $venue->id)
            ->findOrFail($connectionId);

        abort_unless(
            $connection->status === ReceivingConnection::STATUS_VERIFIED
                && $connection->webhook_secret_ciphertext !== null,
            409,
            'Only a verified receiving connection can be selected.'
        );

        DB::transaction(function () use ($venue, $connection, $request): void {
            $lockedVenue = $this->venueRepo->findByIdWithLock($venue->id);
            abort_if($lockedVenue === null, 404, 'Cơ sở thể thao không tồn tại.');
            $lockedConnection = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
            abort_unless($lockedConnection->status === ReceivingConnection::STATUS_VERIFIED, 409, 'This receiving connection is no longer verified.');

            $lockedVenue->forceFill(['current_receiving_connection_id' => $lockedConnection->id])->save();
            app(SePayReceivingConnectionService::class)->recordSelection($lockedConnection, $request->user());
        });

        return response()->json([
            'data' => ReceivingConnectionResource::make($connection->fresh()),
            'message' => 'Receiving connection selected for this venue.',
        ]);
    }

    private function authorizeVenue(Request $request, int $organizationId, int $venueId): Venue
    {
        $venue = $this->findScopedVenue($organizationId, $venueId);
        $user = $request->user();

        abort_unless(
            $user !== null
                && $user->hasRoleInOrganization($organizationId, OrganizationMember::ROLE_ORGANIZATION_OWNER)
                && $user->canManageVenue($venue),
            403,
            'Only an organization owner can manage this receiving connection.'
        );

        return $venue;
    }

    private function findScopedVenue(int $organizationId, int $venueId): Venue
    {
        $venue = $this->venueRepo->findById($venueId);
        abort_if($venue === null || (int) $venue->organization_id !== $organizationId, 404, 'Cơ sở thể thao không tồn tại.');

        return $venue;
    }
}
