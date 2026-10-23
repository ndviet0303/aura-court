<?php

namespace App\Http\Controllers\Chat;

use App\Contracts\Repositories\ConversationRepositoryInterface;
use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Chat\Actions\OpenConversation;
use App\Domain\Chat\Actions\SendChatMessage;
use App\Domain\Chat\Actions\SendVoucherGift;
use App\Domain\Chat\DTOs\ChatMessageData;
use App\Domain\Chat\DTOs\OpenConversationData;
use App\Domain\Chat\Rules\ConversationAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\ListChatMessagesRequest;
use App\Http\Requests\Chat\OpenConversationRequest;
use App\Http\Requests\Chat\SendChatMessageRequest;
use App\Http\Requests\Chat\SendVoucherGiftRequest;
use App\Http\Resources\Chat\ChatMessageResource;
use App\Http\Resources\Chat\ConversationResource;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Venue;
use App\Models\VoucherCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** FR-118 chat (UC-7.13–UC-7.15) and FR-111 voucher gifts (UC-7.3). */
class ConversationController extends Controller
{
    public function __construct(private readonly ConversationRepositoryInterface $conversations) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->conversations->forUser($request->user(), 30);

        return response()->json([
            'data' => ConversationResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function store(OpenConversationRequest $request, OpenConversation $action): JsonResponse
    {
        $conversation = $action->execute($request->user(), OpenConversationData::from($request));

        return response()->json(['conversation' => ConversationResource::make($conversation->load(['userA', 'userB', 'venue', 'organization', 'latestMessage']))]);
    }

    public function messages(ListChatMessagesRequest $request, Conversation $conversation): JsonResponse
    {
        $side = ConversationAccess::sideOrFail($conversation, $request->user());
        $after = $request->validated('after');
        $messages = $this->conversations->messages($conversation, $after === null ? null : (int) $after, $request->validated('before') ? (int) $request->validated('before') : null, 50);

        return response()->json([
            'conversation' => ConversationResource::make($conversation->load(['userA', 'userB', 'venue', 'organization', 'latestMessage'])),
            'data' => $messages->map(fn (ConversationMessage $message) => new ChatMessageResource($message, $conversation, $side))->values(),
        ]);
    }

    public function send(SendChatMessageRequest $request, Conversation $conversation, SendChatMessage $action): JsonResponse
    {
        $message = $action->execute($conversation, $request->user(), ChatMessageData::from($request));

        return response()->json(['message' => new ChatMessageResource($message, $conversation, $message->sender_side)], 201);
    }

    public function gift(SendVoucherGiftRequest $request, Conversation $conversation, SendVoucherGift $action): JsonResponse
    {
        $message = $action->execute($conversation, $request->user(), (string) $request->validated('code'), $request->validated('note'));

        return response()->json(['message' => new ChatMessageResource($message, $conversation, $message->sender_side)], 201);
    }

    /** BR-143: the free codes of the venue's running private programs the owner may gift. */
    public function giftableVouchers(Request $request, Venue $venue, PromotionProgramRepositoryInterface $promotions): JsonResponse
    {
        if (! $request->user()->canManageVenue($venue)) {
            ConversationAccess::fail('VOUCHER_OWNER_ONLY', 'Chỉ chủ cơ sở được tặng voucher.', 403);
        }

        return response()->json(['data' => $promotions->giftableCodes($venue->id, now())->map(fn (VoucherCode $code) => [
            'code' => $code->code,
            'program' => $code->program->name,
            'discount_kind' => $code->program->discount_kind,
            'percent_bp' => $code->program->percent_bp,
            'amount' => $code->program->amount,
            'max_discount' => $code->program->max_discount,
            'min_rental' => $code->program->min_rental,
            'courts' => $code->program->courts->pluck('name')->all(),
            'ends_at' => $code->program->ends_at?->toIso8601String(),
        ])->values()]);
    }
}
