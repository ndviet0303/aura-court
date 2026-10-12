<?php

namespace App\Http\Controllers\Court;

use App\Domain\Reservation\Actions\CreateReview;
use App\Domain\Reservation\DTOs\CreateReviewData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Court\StoreReviewRequest;
use App\Models\Court;

class ReviewController extends Controller
{
    public function index(Court $court)
    {
        return $court->reviews()->where('is_published', true)->with('user:id,name')->latest()->paginate(20);
    }

    public function store(StoreReviewRequest $request, CreateReview $action)
    {
        return response()->json($action->execute(CreateReviewData::from($request)), 201);
    }
}
