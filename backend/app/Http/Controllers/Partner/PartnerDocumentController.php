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
                'mime_type' => $doc->mime_type,
                'file_size' => (int) $doc->file_size,
                'version' => (int) $doc->version,
                'status' => $doc->status,
                'read_url' => $this->storage->temporaryReadUrl($doc->file_path),
                'created_at' => $doc->created_at?->toIso8601String(),
                'application_version' => (int) $doc->hostKyc()->value('version'),
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
