<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerService
{
    /**
     * @param array<int, array{account_id?: int, account_code?: string, side: string, amount: int, source_type?: string, source_id?: int, metadata?: array}> $entries
     */
    public function post(array $attributes, array $entries): Journal
    {
        if (count($entries) < 2) {
            throw new InvalidArgumentException('A journal requires at least one debit and one credit entry.');
        }

        $debit = 0;
        $credit = 0;
        foreach ($entries as $entry) {
            $side = strtolower((string) ($entry['side'] ?? ''));
            $amount = (int) ($entry['amount'] ?? 0);
            if (! in_array($side, ['debit', 'credit'], true) || $amount <= 0) {
                throw new InvalidArgumentException('Journal entries must have a positive debit or credit amount.');
            }
            $side === 'debit' ? $debit += $amount : $credit += $amount;
        }
        if ($debit !== $credit) {
            throw new InvalidArgumentException('Journal debit and credit totals must balance.');
        }

        return DB::transaction(function () use ($attributes, $entries, $debit): Journal {
            $journal = Journal::create(array_merge($attributes, [
                'total_debit' => $debit,
                'total_credit' => $debit,
                'posted_at' => $attributes['posted_at'] ?? now(),
            ]));
            foreach (array_values($entries) as $index => $entry) {
                $accountId = $entry['account_id'] ?? Account::query()
                    ->where('organization_id', $attributes['organization_id'] ?? null)
                    ->where('code', $entry['account_code'] ?? '')
                    ->value('id');
                if (! $accountId) {
                    throw new InvalidArgumentException('Journal account was not found.');
                }
                $journal->entries()->create([
                    'organization_id' => $attributes['organization_id'] ?? null,
                    'account_id' => $accountId,
                    'side' => strtolower($entry['side']),
                    'amount' => (int) $entry['amount'],
                    'line_number' => $index + 1,
                    'source_type' => $entry['source_type'] ?? null,
                    'source_id' => $entry['source_id'] ?? null,
                    'metadata' => $entry['metadata'] ?? null,
                ]);
            }
            return $journal->load('entries');
        });
    }

    public function systemAccounts(?int $organizationId): array
    {
        $definitions = [
            'cash_clearing' => ['name' => 'Cash clearing', 'type' => 'asset'],
            'provider_fee_expense' => ['name' => 'Provider fees', 'type' => 'expense'],
            'platform_commission' => ['name' => 'Platform commission', 'type' => 'revenue'],
            'tenant_payable' => ['name' => 'Tenant payable', 'type' => 'liability'],
            'reserve' => ['name' => 'Tenant reserve', 'type' => 'liability'],
            'customer_refunds' => ['name' => 'Customer refunds', 'type' => 'liability'],
            'wallet_liability' => ['name' => 'Tenant wallet liability', 'type' => 'liability'],
        ];
        $accounts = [];
        foreach ($definitions as $code => $definition) {
            $accounts[$code] = Account::firstOrCreate([
                'organization_id' => $organizationId,
                'code' => $code,
            ], array_merge($definition, ['currency' => 'VND', 'is_system' => true, 'status' => 'active']));
        }
        return $accounts;
    }
}
