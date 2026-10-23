<?php

namespace App\Http\Controllers\Community;

use App\Contracts\Storage\FileStorageServiceInterface;
use App\Domain\Community\Actions\ReportContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Community\ReportContentRequest;
use App\Http\Requests\Community\UploadCommunityImageRequest;
use App\Http\Resources\Community\ContentReportResource;
use Illuminate\Http\JsonResponse;

/** FR-132 (UC-7.9) reports, and the D9 image upload that posts and reviews use. */
class ContentReportController extends Controller
{
    public function store(ReportContentRequest $request, ReportContent $action): JsonResponse
    {
        $report = $action->execute($request->user(), (string) $request->validated('type'), (int) $request->validated('id'), (string) $request->validated('reason'));

        return response()->json(['report' => ContentReportResource::make($report->load(['reporter:id,name', 'reportable.user:id,name,avatar_url']))], 201);
    }

    public function uploadImage(UploadCommunityImageRequest $request, FileStorageServiceInterface $files): JsonResponse
    {
        return response()->json(['url' => $files->store($request->file('image'), 'community')['url']], 201);
    }
}
