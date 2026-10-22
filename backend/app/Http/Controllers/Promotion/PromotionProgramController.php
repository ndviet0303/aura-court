<?php

namespace App\Http\Controllers\Promotion;

use App\Contracts\Repositories\PromotionProgramRepositoryInterface;
use App\Domain\Promotion\Actions\CreatePromotionProgram;
use App\Domain\Promotion\Actions\StopPromotionProgram;
use App\Domain\Promotion\Actions\UpdatePromotionProgram;
use App\Domain\Promotion\DTOs\CreatePromotionProgramData;
use App\Domain\Promotion\DTOs\UpdatePromotionProgramData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\StorePromotionProgramRequest;
use App\Http\Requests\Promotion\UpdatePromotionProgramRequest;
use App\Http\Resources\Promotion\PromotionProgramCodesResource;
use App\Http\Resources\Promotion\PromotionProgramResource;
use App\Models\PromotionProgram;
use App\Models\Venue;
use App\Traits\AuthorizesPromotionVenue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Promotion programs of a venue (design D7, D8, D14); owner-only routes, and programs are never deleted. */
class PromotionProgramController extends Controller
{
    use AuthorizesPromotionVenue;

    public function __construct(private readonly PromotionProgramRepositoryInterface $programs) {}

    public function index(Request $request, Venue $venue): JsonResponse
    {
        $this->authorizeVenue($request, $venue);

        return response()->json(['data' => PromotionProgramResource::collection($this->programs->forVenue((int) $venue->id))]);
    }

    public function store(StorePromotionProgramRequest $request, Venue $venue, CreatePromotionProgram $action): JsonResponse
    {
        $program = $action->execute(CreatePromotionProgramData::from($request));

        return response()->json(['data' => PromotionProgramResource::make($this->programs->loadDetail($program))], 201);
    }

    public function show(Request $request, PromotionProgram $program): JsonResponse
    {
        $this->authorizeVenue($request, $program->venue);

        return response()->json(['data' => PromotionProgramResource::make($this->programs->loadDetail($program))]);
    }

    public function update(UpdatePromotionProgramRequest $request, PromotionProgram $program, UpdatePromotionProgram $action): JsonResponse
    {
        $change = $action->execute(UpdatePromotionProgramData::from($request));

        return response()->json(['data' => PromotionProgramResource::make($this->programs->loadDetail($change->program))]);
    }

    public function stop(Request $request, PromotionProgram $program, StopPromotionProgram $action): JsonResponse
    {
        $this->authorizeVenue($request, $program->venue);
        $change = $action->execute((int) $program->id);

        return response()->json(['data' => PromotionProgramResource::make($this->programs->loadDetail($change->program))]);
    }

    public function codes(Request $request, PromotionProgram $program): JsonResponse
    {
        $this->authorizeVenue($request, $program->venue);

        return response()->json(['data' => PromotionProgramCodesResource::make($this->programs->loadCodes($program))]);
    }

    private function authorizeVenue(Request $request, Venue $venue): void
    {
        abort_unless($this->managesPromotionVenue($request, $venue), 403, 'Bạn không có quyền quản lý chương trình khuyến mãi của cơ sở này.');
    }
}
