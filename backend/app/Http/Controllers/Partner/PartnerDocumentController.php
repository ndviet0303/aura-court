<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partner;

use App\Contracts\Storage\PartnerDocumentStorageInterface;
use App\Domain\Partner\Actions\AttachPartnerDocument;
use App\Domain\Partner\DTOs\AttachPartnerDocumentData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\AttachPartnerDocumentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerDocumentController extends Controller
{
    public function __construct(
        private readonly PartnerDocumentStorageInterface $storage,
    ) {}

    public function upload(
        AttachPartnerDocumentRequest $request,
        int $id,
        AttachPartnerDocument $action
    ): JsonResponse {
        $dto = AttachPartnerDocumentData::from($request, $id);
        $doc = $action->execute($dto);

        return response()->json([
            'data' => [
                'id' => $doc->id,
                'document_type' => $doc->document_type,
                'file_name' => $doc->file_name,
                'file_size' => $doc->file_size,
                'mime_type' => $doc->mime_type,
                'created_at' => $doc->created_at,
            ],
            'message' => 'Tải lên tài liệu thành công.',
        ], 201);
    }

    public function download(Request $request): StreamedResponse|JsonResponse
    {
        $path = (string) $request->query('path', '');

        if (empty($path) || ! $this->storage->exists($path)) {
            return response()->json(['message' => 'Tài liệu không tồn tại hoặc đã hết hạn.'], 404);
        }

        $fileName = basename($path);

        return $this->storage->downloadResponse($path, $fileName);
    }
}
