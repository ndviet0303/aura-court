<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use App\Services\Organization\OrganizationInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The invitee's side of a staff invitation (BR-19): list, accept, decline. */
class InvitationResponseController extends Controller
{
    public function __construct(private readonly OrganizationInvitationService $invitations) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['invitations' => $this->invitations->pendingFor($request->user())]);
    }

    public function accept(Request $request, OrganizationInvitation $invitation): JsonResponse
    {
        $result = $this->invitations->accept($request->user(), $invitation, $request->ip());

        return response()->json([
            'message' => $result['already'] ? 'Lời mời đã được chấp nhận trước đó.' : 'Chấp nhận lời mời thành công.',
            'invitation' => $result['invitation'],
            'member' => $result['member']?->load('user'),
        ]);
    }

    public function decline(Request $request, OrganizationInvitation $invitation): JsonResponse
    {
        return response()->json([
            'message' => 'Đã từ chối lời mời.',
            'invitation' => $this->invitations->decline($request->user(), $invitation, $request->ip()),
        ]);
    }
}
