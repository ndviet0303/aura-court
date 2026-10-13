<?php

namespace App\Services\Finance;

use App\Contracts\Payment\WebhookEvent;
use App\Models\Booking;
use App\Models\Journal;
use App\Models\OwnerPaymentAttempt;
use App\Models\OwnerPaymentReceipt;
use App\Models\OwnerRefundObligation;
use App\Models\Payment;
use App\Models\PlatformProviderReceipt;
use App\Models\ProviderEvent;
use App\Models\Reservation;
use Illuminate\Database\QueryException;

class FinancialPostingService
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function postReservationPayment(Reservation $reservation, int $amount, string $providerReference, array $metadata = []): Journal
    {
        $organizationId = $reservation->organization_id;

        return $this->postCollection(
            organizationId: $organizationId,
            eventType: 'reservation.payment',
            eventId: "reservation:{$reservation->id}:{$providerReference}",
            reference: $reservation->code,
            amount: $amount,
            sourceType: Reservation::class,
            sourceId: $reservation->id,
            metadata: $metadata,
        );
    }

    public function postLegacyBookingPayment(Booking $booking, Payment $payment): Journal
    {
        $organizationId = $booking->court?->venue?->organization_id;

        return $this->postCollection(
            organizationId: $organizationId,
            eventType: 'booking.payment',
            eventId: "booking:{$booking->id}:payment:{$payment->id}",
            reference: $booking->code,
            amount: (int) $payment->amount,
            sourceType: Payment::class,
            sourceId: $payment->id,
            metadata: ['provider' => $payment->provider, 'external_transaction_id' => $payment->external_transaction_id],
        );
    }

    public function postProviderCollection(ProviderEvent|WebhookEvent $event, ?int $organizationId = null): ?Journal
    {
        $status = $event instanceof ProviderEvent ? strtoupper($event->status) : $event->status;
        if (! in_array($status, ['PAID', 'COMPLETED', 'SUCCEEDED', 'SUCCESS'], true)) {
            return null;
        }
        $eventId = $event instanceof ProviderEvent ? $event->event_id : $event->eventId;
        $reference = $event instanceof ProviderEvent ? $event->reference : $event->reference;
        $amount = (int) ($event instanceof ProviderEvent ? $event->amount : $event->amount);
        if ($amount <= 0) {
            return null;
        }

        return $this->postCollection(
            organizationId: $organizationId ?? ($event instanceof ProviderEvent ? $event->organization_id : null),
            eventType: 'provider.collection',
            eventId: "provider:{$eventId}",
            reference: $reference,
            amount: $amount,
            sourceType: ProviderEvent::class,
            sourceId: $event instanceof ProviderEvent ? $event->id : null,
            metadata: $event instanceof ProviderEvent ? $event->payload : $event->data,
        );
    }

    public function postDirectOwnerReceipt(OwnerPaymentReceipt $receipt): Journal
    {
        if ($receipt->status !== 'allocated' || ! $receipt->payment_attempt_id) {
            throw new \InvalidArgumentException('Only an allocated direct-owner receipt can be posted.');
        }

        $attempt = $receipt->attempt()->firstOrFail();
        $allocation = $receipt->allocation()->firstOrFail();
        if (
            $attempt->status !== OwnerPaymentAttempt::STATUS_PAID
            || (int) $attempt->receiving_connection_id !== (int) $receipt->receiving_connection_id
            || (int) $attempt->organization_id !== (int) $receipt->organization_id
            || (int) $attempt->venue_id !== (int) $receipt->venue_id
            || (int) $allocation->payment_attempt_id !== (int) $attempt->id
            || (int) $allocation->receiving_connection_id !== (int) $receipt->receiving_connection_id
            || (int) $allocation->amount !== (int) $receipt->amount
            || $allocation->currency !== $receipt->currency
        ) {
            throw new \InvalidArgumentException('Direct-owner receipt allocation does not match its payment attempt.');
        }
        $accounts = $this->ledger->systemAccounts((int) $receipt->organization_id);

        return $this->postEntityJournal([
            'organization_id' => $receipt->organization_id,
            'ledger_entity' => 'owner',
            'payment_model' => 'direct_owner_v1',
            'event_type' => 'owner.direct_receipt',
            'event_id' => "owner-receipt:{$receipt->id}",
            'reference' => $attempt->reference,
            'currency' => $receipt->currency,
            'description' => 'Verified direct-owner receipt',
            'metadata' => [
                'provider' => $receipt->provider,
                'receiving_connection_id' => $receipt->receiving_connection_id,
                'receipt_id' => $receipt->id,
                'purpose' => $attempt->purpose,
                'purpose_id' => $attempt->purpose_id,
                'commission' => 0,
            ],
        ], [
            ['account_id' => $accounts['owner_bank_cash']->id, 'side' => 'debit', 'amount' => (int) $receipt->amount, 'source_type' => OwnerPaymentReceipt::class, 'source_id' => $receipt->id],
            ['account_id' => $accounts['owner_direct_revenue']->id, 'side' => 'credit', 'amount' => (int) $receipt->amount, 'source_type' => OwnerPaymentReceipt::class, 'source_id' => $receipt->id],
        ]);
    }

    public function postPlatformSubscriptionReceipt(PlatformProviderReceipt $receipt): Journal
    {
        if (! $receipt->verified_at || (int) $receipt->amount <= 0) {
            throw new \InvalidArgumentException('Only a verified platform subscription receipt can be posted.');
        }

        $attempt = $receipt->paymentAttempt()->firstOrFail();
        if (
            $attempt->status !== 'succeeded'
            || (int) $attempt->amount !== (int) $receipt->amount
            || $attempt->currency !== $receipt->currency
            || $attempt->provider !== $receipt->provider
        ) {
            throw new \InvalidArgumentException('Platform receipt does not match its verified payment attempt.');
        }
        $accounts = $this->ledger->systemAccounts((int) $attempt->organization_id);

        return $this->postEntityJournal([
            'organization_id' => $attempt->organization_id,
            'ledger_entity' => 'platform',
            'payment_model' => 'platform_subscription_v1',
            'event_type' => 'platform.subscription_receipt',
            'event_id' => "platform-subscription-receipt:{$receipt->id}",
            'reference' => $attempt->reference_code,
            'currency' => $receipt->currency,
            'description' => 'Verified platform subscription receipt',
            'metadata' => [
                'provider' => $receipt->provider,
                'payment_attempt_id' => $attempt->id,
                'subscription_id' => $attempt->subscription_id,
                'receipt_id' => $receipt->id,
                'commission' => 0,
            ],
        ], [
            ['account_id' => $accounts['platform_bank_cash']->id, 'side' => 'debit', 'amount' => (int) $receipt->amount, 'source_type' => PlatformProviderReceipt::class, 'source_id' => $receipt->id],
            ['account_id' => $accounts['platform_subscription_revenue']->id, 'side' => 'credit', 'amount' => (int) $receipt->amount, 'source_type' => PlatformProviderReceipt::class, 'source_id' => $receipt->id],
        ]);
    }

    public function postDirectOwnerRefund(OwnerRefundObligation $refund): ?Journal
    {
        if ($refund->status !== OwnerRefundObligation::STATUS_ACKNOWLEDGED || (int) $refund->amount <= 0) {
            return null;
        }

        $allocations = $refund->allocations()->with('paymentAllocation')->get();
        $allocatedRefund = (int) $allocations->sum('amount');
        if ($allocatedRefund !== (int) $refund->amount || $allocations->isEmpty()) {
            throw new \InvalidArgumentException('Acknowledged refund does not match its original payer allocations.');
        }

        foreach ($allocations as $refundAllocation) {
            $source = $refundAllocation->paymentAllocation;
            if (
                ! $source
                || (int) $source->organization_id !== (int) $refund->organization_id
                || (int) $source->venue_id !== (int) $refund->venue_id
                || $source->currency !== $refund->currency
                || (int) $refundAllocation->gross_amount > (int) $source->amount
            ) {
                throw new \InvalidArgumentException('Refund allocation does not match its original owner receipt.');
            }
        }

        $accounts = $this->ledger->systemAccounts((int) $refund->organization_id);

        return $this->postEntityJournal([
            'organization_id' => $refund->organization_id,
            'ledger_entity' => 'owner',
            'payment_model' => 'direct_owner_v1',
            'event_type' => 'owner.direct_refund_acknowledged',
            'event_id' => 'owner-refund:'.$refund->id,
            'reference' => $refund->paymentAttempt?->reference ?? $refund->reference_code,
            'currency' => $refund->currency,
            'description' => 'Owner refund acknowledged by the payer',
            'metadata' => [
                'refund_obligation_id' => $refund->id,
                'refund_reference' => $refund->reference_code,
                'payment_attempt_id' => $refund->payment_attempt_id,
                'allocation_ids' => $allocations->pluck('owner_payment_allocation_id')->map(fn ($id) => (int) $id)->values()->all(),
                'gross_amount' => (int) $refund->gross_amount,
                'fee_amount' => (int) $refund->fee_amount,
                'external_transfer_reference' => $refund->execution_reference,
                'commission' => 0,
            ],
        ], [
            ['account_id' => $accounts['owner_direct_revenue']->id, 'side' => 'debit', 'amount' => (int) $refund->amount, 'source_type' => OwnerRefundObligation::class, 'source_id' => $refund->id],
            ['account_id' => $accounts['owner_bank_cash']->id, 'side' => 'credit', 'amount' => (int) $refund->amount, 'source_type' => OwnerRefundObligation::class, 'source_id' => $refund->id],
        ]);
    }

    public function postRefund(int $organizationId, string $reference, int $amount, string $refundReference, array $metadata = []): Journal
    {
        $accounts = $this->ledger->systemAccounts($organizationId);

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'refund',
            'event_id' => "refund:{$refundReference}",
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Compensating refund journal',
            'metadata' => $metadata,
        ], [
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'debit', 'amount' => $amount],
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'credit', 'amount' => $amount],
        ]);
    }

    public function postPosSale(int $organizationId, int $orderId, int $amount, string $paymentMethod, string $reference): Journal
    {
        $eventId = "pos:{$orderId}";
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'pos.sale')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);
        $debit = $paymentMethod === 'wallet' ? $accounts['wallet_liability'] : $accounts['cash_clearing'];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'pos.sale',
            'event_id' => $eventId,
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Connected POS sale',
        ], [
            ['account_id' => $debit->id, 'side' => 'debit', 'amount' => $amount],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $amount],
        ]);
    }

    public function postWalletCredit(int $organizationId, int $walletId, int $amount, string $reference): Journal
    {
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'wallet.credit')->where('event_id', "wallet:{$walletId}:credit:{$reference}")->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'wallet.credit',
            'event_id' => "wallet:{$walletId}:credit:{$reference}",
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Tenant wallet liability issued',
        ], [
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'debit', 'amount' => $amount],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => $amount],
        ]);
    }

    public function postWalletDeposit(int $organizationId, int $walletId, int $amount, string $reference, ?string $sourceType = null, ?int $sourceId = null): Journal
    {
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'wallet.deposit')->where('event_id', "wallet:{$walletId}:deposit:{$reference}")->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'wallet.deposit',
            'event_id' => "wallet:{$walletId}:deposit:{$reference}",
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Tenant wallet deposit funded via provider',
            'metadata' => ['wallet_account_id' => $walletId],
        ], [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => $sourceType, 'source_id' => $sourceId],
        ]);
    }

    public function postWalletSpend(int $organizationId, int $walletId, int $amount, string $reference): Journal
    {
        $eventId = "wallet:{$walletId}:spend:{$reference}";
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'wallet.spend')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'wallet.spend',
            'event_id' => $eventId,
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Tenant wallet liability redeemed',
        ], [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => $amount],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $amount],
        ]);
    }

    public function postWalletBookingPayment(Booking $booking, Payment $payment, int $walletAccountId): Journal
    {
        $organizationId = $booking->court?->venue?->organization_id;
        $eventId = "booking:{$booking->id}:payment:{$payment->id}";
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'booking.wallet_payment')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $amount = (int) $payment->amount;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => Payment::class, 'source_id' => $payment->id],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => Payment::class, 'source_id' => $payment->id],
        ];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'booking.wallet_payment',
            'event_id' => $eventId,
            'reference' => $booking->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Booking payment via wallet balance',
            'metadata' => ['provider' => 'wallet', 'wallet_account_id' => $walletAccountId, 'commission' => 0],
        ], $entries);
    }

    public function postWalletBookingRefund(Booking $booking, int $amount, int $walletAccountId, ?string $refundReference = null): Journal
    {
        $organizationId = $booking->court?->venue?->organization_id;
        $eventId = "booking:{$booking->id}:refund:".($refundReference ?? 'full');
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'booking.wallet_refund')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => Booking::class, 'source_id' => $booking->id],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => Booking::class, 'source_id' => $booking->id],
        ];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'booking.wallet_refund',
            'event_id' => $eventId,
            'reference' => $booking->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Booking cancellation refund to wallet',
            'metadata' => ['wallet_account_id' => $walletAccountId, 'commission' => 0],
        ], $entries);
    }

    public function postWalletReservationPayment(Reservation $reservation, int $amount, int $walletAccountId, ?int $walletTxId = null): Journal
    {
        $organizationId = $reservation->organization_id;
        $eventId = "reservation:{$reservation->id}:payment:".($walletTxId ?? $reservation->id);
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'reservation.wallet_payment')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $amount = (int) $amount;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => Reservation::class, 'source_id' => $reservation->id],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => Reservation::class, 'source_id' => $reservation->id],
        ];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'reservation.wallet_payment',
            'event_id' => $eventId,
            'reference' => $reservation->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Reservation payment via wallet balance',
            'metadata' => ['provider' => 'wallet', 'wallet_account_id' => $walletAccountId, 'commission' => 0],
        ], $entries);
    }

    public function postWalletReservationRefund(Reservation $reservation, int $amount, int $walletAccountId, ?string $refundReference = null): Journal
    {
        $organizationId = $reservation->organization_id;
        $eventId = "reservation:{$reservation->id}:refund:".($refundReference ?? 'full');
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', 'reservation.wallet_refund')->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => Reservation::class, 'source_id' => $reservation->id],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => Reservation::class, 'source_id' => $reservation->id],
        ];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'reservation.wallet_refund',
            'event_id' => $eventId,
            'reference' => $reservation->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Reservation cancellation refund to wallet',
            'metadata' => ['wallet_account_id' => $walletAccountId, 'commission' => 0],
        ], $entries);
    }

    public function postTournamentRegistrationPayment(int $organizationId, int $tournamentId, int $participantId, int $amount, int $walletAccountId, ?string $reference = null): Journal
    {
        $eventId = "tournament:{$tournamentId}:participant:{$participantId}";
        $existing = Journal::query()
            ->where('organization_id', $organizationId)
            ->where('event_type', 'tournament.registration')
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            return $existing->load('entries');
        }

        $amount = (int) $amount;
        $accounts = $this->ledger->systemAccounts($organizationId);

        $entries = [
            [
                'account_id' => $accounts['wallet_liability']->id,
                'side' => 'debit',
                'amount' => $amount,
                'source_type' => 'App\\Models\\TournamentParticipant',
                'source_id' => $participantId,
            ],
            [
                'account_id' => $accounts['tenant_payable']->id,
                'side' => 'credit',
                'amount' => $amount,
                'source_type' => 'App\\Models\\TournamentParticipant',
                'source_id' => $participantId,
            ],
        ];

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'tournament.registration',
            'event_id' => $eventId,
            'reference' => $reference ?? "TRN-{$tournamentId}-{$participantId}",
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Tournament registration fee funded via wallet',
            'metadata' => [
                'provider' => 'wallet',
                'tournament_id' => $tournamentId,
                'participant_id' => $participantId,
                'wallet_account_id' => $walletAccountId,
                'commission' => 0,
            ],
        ], $entries);
    }

    public function compensate(Journal $original, string $eventId, string $reason, array $metadata = []): Journal
    {
        $entries = $original->entries()->orderBy('line_number')->get()->map(function ($entry) {
            return [
                'account_id' => $entry->account_id,
                'side' => $entry->side === 'debit' ? 'credit' : 'debit',
                'amount' => $entry->amount,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'metadata' => $entry->metadata,
            ];
        })->all();

        return $this->ledger->post([
            'organization_id' => $original->organization_id,
            'event_type' => 'compensation',
            'event_id' => $eventId,
            'reference' => $original->reference,
            'currency' => $original->currency ?: config('finance.currency', 'VND'),
            'description' => $reason,
            'metadata' => $metadata + ['reverses_journal_id' => $original->id],
        ], $entries);
    }

    private function postCollection(?int $organizationId, string $eventType, string $eventId, ?string $reference, int $amount, string $sourceType, ?int $sourceId, array $metadata): Journal
    {
        $existing = Journal::query()->where('organization_id', $organizationId)->where('event_type', $eventType)->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->load('entries');
        }
        $providerFee = intdiv($amount * (int) config('finance.provider_fee_bps', 0), 10000);
        $tenantPayable = $amount - $providerFee;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $tenantPayable, 'source_type' => $sourceType, 'source_id' => $sourceId],
        ];
        if ($providerFee > 0) {
            $entries[] = ['account_id' => $accounts['provider_fee_expense']->id, 'side' => 'credit', 'amount' => $providerFee, 'source_type' => $sourceType, 'source_id' => $sourceId];
        }

        $metadata['commission'] = 0;
        $metadata['provider_fee'] = $providerFee;

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Gross payment collection',
            'metadata' => $metadata,
        ], $entries);
    }

    private function postEntityJournal(array $attributes, array $entries): Journal
    {
        $existing = Journal::query()
            ->where('organization_id', $attributes['organization_id'])
            ->where('event_type', $attributes['event_type'])
            ->where('event_id', $attributes['event_id'])
            ->first();
        if ($existing) {
            return $existing->load('entries');
        }

        try {
            return $this->ledger->post($attributes, $entries);
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (! str_contains($message, 'journals') || (! str_contains($message, 'unique') && ! str_contains($message, 'duplicate'))) {
                throw $exception;
            }

            return Journal::query()
                ->where('organization_id', $attributes['organization_id'])
                ->where('event_type', $attributes['event_type'])
                ->where('event_id', $attributes['event_id'])
                ->firstOrFail()
                ->load('entries');
        }
    }
}
