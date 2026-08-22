<?php

namespace App\Services;

use App\Models\IdempotencyRecord;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyService
{
    public function run(Request $request, callable $operation): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            abort(400, 'Idempotency-Key header is required for v2 state-changing commands.');
        }

        $organizationId = app(TenantContext::class)->getOrganizationId();
        $userId = $request->user()?->id;
        if (! $organizationId || ! $userId) {
            abort(400, 'Organization context and authentication are required.');
        }

        $requestHash = $this->requestHash($request);
        $command = $request->method().' '.$request->path();

        try {
            return DB::transaction(function () use ($operation, $key, $organizationId, $userId, $requestHash, $command): Response {
                $record = IdempotencyRecord::query()
                    ->where('organization_id', $organizationId)
                    ->where('user_id', $userId)
                    ->where('key', $key)
                    ->lockForUpdate()
                    ->first();

                if ($record && $record->expires_at?->isPast()) {
                    $record->delete();
                    $record = null;
                }

                if ($record) {
                    if (! hash_equals($record->request_hash, $requestHash)) {
                        abort(409, 'Idempotency-Key was already used with different request content.');
                    }

                    if ($record->response_body === null || $record->response_status === null) {
                        abort(409, 'The idempotent command is still being processed.');
                    }

                    return response()->json($record->response_body, $record->response_status);
                }

                $record = IdempotencyRecord::create([
                    'organization_id' => $organizationId,
                    'user_id' => $userId,
                    'key' => $key,
                    'command' => $command,
                    'request_hash' => $requestHash,
                    'expires_at' => now()->addHours(24),
                ]);

                $result = $operation();
                $response = $result instanceof Response ? $result : response()->json($result);
                $body = method_exists($response, 'getData') ? $response->getData(true) : json_decode($response->getContent(), true);

                $record->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => is_array($body) ? $body : ['data' => $body],
                ]);

                return $response;
            });
        } catch (QueryException $exception) {
            // A concurrent request with the same key can win the unique insert
            // between the initial lookup and create. Once its transaction has
            // committed, return the same stored outcome as a normal replay.
            if (! $this->isIdempotencyUniqueViolation($exception)) {
                throw $exception;
            }

            $record = IdempotencyRecord::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $userId)
                ->where('key', $key)
                ->firstOrFail();
            if (! hash_equals($record->request_hash, $requestHash)) {
                abort(409, 'Idempotency-Key was already used with different request content.');
            }

            return response()->json($record->response_body ?? [], $record->response_status ?? 409);
        }
    }

    private function requestHash(Request $request): string
    {
        $payload = $request->all();
        $this->sortRecursively($payload);

        return hash('sha256', json_encode([
            'method' => $request->method(),
            'path' => $request->path(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function sortRecursively(mixed &$value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as &$item) {
            $this->sortRecursively($item);
        }
        unset($item);

        if (! array_is_list($value)) {
            ksort($value);
        }
    }

    private function isIdempotencyUniqueViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'idempotency_records')
            && (str_contains($message, 'unique') || str_contains($message, 'duplicate'));
    }
}
