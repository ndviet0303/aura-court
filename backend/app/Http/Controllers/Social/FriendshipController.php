<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Services\Infrastructure\IdempotencyService;
use App\Services\Social\FriendshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function __construct(
        private readonly FriendshipService $friendships,
        private readonly IdempotencyService $idempotency
    ) {}

    /**
     * List all accepted friends for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q') ?? $request->query('search');
        $friends = $this->friendships->getFriends($request->user(), $search);

        return response()->json([
            'friends' => $friends,
            'total' => $friends->count(),
        ]);
    }

    /**
     * List pending requests (incoming and outgoing).
     */
    public function requests(Request $request): JsonResponse
    {
        $requests = $this->friendships->getRequests($request->user());

        return response()->json($requests);
    }

    /**
     * Send a friendship request.
     */
    public function sendRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'addressee_id' => 'required|integer',
        ]);

        return $this->idempotency->run($request, function () use ($request, $data) {
            $friendship = $this->friendships->sendRequest($request->user(), (int) $data['addressee_id']);

            return response()->json([
                'message' => 'Friendship request sent successfully.',
                'friendship' => $friendship,
            ], 201);
        });
    }

    /**
     * Accept a pending friendship request.
     */
    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        return $this->idempotency->run($request, function () use ($request, $friendship) {
            $accepted = $this->friendships->acceptRequest($friendship, $request->user());

            return response()->json([
                'message' => 'Friendship request accepted.',
                'friendship' => $accepted,
            ]);
        });
    }

    /**
     * Decline a pending friendship request.
     */
    public function decline(Request $request, Friendship $friendship): JsonResponse
    {
        return $this->idempotency->run($request, function () use ($request, $friendship) {
            $declined = $this->friendships->declineRequest($friendship, $request->user());

            return response()->json([
                'message' => 'Friendship request declined.',
                'friendship' => $declined,
            ]);
        });
    }

    /**
     * Remove an accepted friendship or cancel a pending request.
     */
    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        $this->friendships->removeFriendship($friendship, $request->user());

        return response()->json([
            'message' => 'Friendship removed successfully.',
        ]);
    }
}
