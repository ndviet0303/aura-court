<?php

namespace App\Services\Payment;

use App\Jobs\ProcessOwnerPaymentReceipt;
use App\Models\ReceivingConnection;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SePayTransactionQueryService
{
    public function __construct(
        private readonly SePayReceivingConnectionService $connections,
        private readonly OwnerPaymentAttemptService $payments,
    ) {}

    public function sync(ReceivingConnection $connection): array
    {
        abort_unless(
            $connection->status === ReceivingConnection::STATUS_VERIFIED
                && $connection->provider_account_id !== null
                && $connection->api_token_version > 0,
            409,
            'A verified SePay connection and query credential are required.'
        );

        try {
            $token = $connection->sepayApiToken();
        } catch (DecryptException) {
            abort(409, 'SePay query credential cannot be decrypted; rotate it before syncing.');
        }
        abort_unless($token !== null, 409, 'A SePay query credential is required.');

        $baseUrl = rtrim((string) config('services.sepay.api_v2_base_url', 'https://userapi.sepay.vn/v2'), '/');
        $query = [
            'bank_account_id' => $connection->provider_account_id,
            'transfer_type' => 'in',
            'transaction_date_sort' => 'asc',
            'per_page' => 100,
        ];
        if ($connection->last_queried_transaction_id !== null) {
            $query['since_id'] = $connection->last_queried_transaction_id;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->get($baseUrl.'/transactions', $query);
        $response->throw();

        $transactions = $this->extractTransactions($response->json());
        $latestId = $connection->last_queried_transaction_id;
        $received = 0;

        foreach (array_slice($transactions, 0, 100) as $transaction) {
            if (! is_array($transaction)) {
                throw new RuntimeException('SePay API returned an invalid transaction row.');
            }

            $verified = $this->connections->recordAuthenticatedQueryEvent($connection, $transaction);
            $receipt = $this->payments->receiveVerifiedEvent($connection, $verified);
            if ($this->payments->claimReceiptForDispatch((int) $receipt->id)) {
                ProcessOwnerPaymentReceipt::dispatch((int) $receipt->id)->afterCommit();
            }
            $received++;

            $id = (string) ($transaction['id'] ?? '');
            if (ctype_digit($id) && ($latestId === null || (int) $id > (int) $latestId)) {
                $latestId = $id;
            }
        }

        if ($latestId !== $connection->last_queried_transaction_id) {
            DB::transaction(function () use ($connection, $latestId): void {
                $locked = ReceivingConnection::query()->lockForUpdate()->findOrFail($connection->id);
                if ($locked->api_token_version === $connection->api_token_version
                    && $locked->provider_account_id === $connection->provider_account_id) {
                    $locked->last_queried_transaction_id = $latestId;
                    $locked->save();
                }
            });
        }

        return [
            'transactions_received' => $received,
            'last_queried_transaction_id' => $latestId,
        ];
    }

    private function extractTransactions(mixed $response): array
    {
        if (! is_array($response)) {
            throw new RuntimeException('SePay API returned an invalid response.');
        }

        $rows = $response['data'] ?? $response['transactions'] ?? [];
        if (is_array($rows) && ! array_is_list($rows)) {
            $rows = $rows['transactions'] ?? $rows['data'] ?? [];
        }
        if (! is_array($rows)) {
            throw new RuntimeException('SePay API returned an invalid transaction list.');
        }

        return array_is_list($rows) ? $rows : [];
    }
}
