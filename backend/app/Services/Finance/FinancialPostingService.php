<?php

namespace App\Services\Finance;

use App\Contracts\Payment\WebhookEvent;
use App\Models\Booking;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\ProviderEvent;
use App\Models\Reservation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

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

    public function postMarketplaceSale(int $organizationId, int $listingId, int $buyerAmount, int $sellerRefund, int $fee, string $buyerReference): Journal
    {
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => $buyerAmount],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $sellerRefund],
        ];
        if ($fee > 0) {
            $entries[] = ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => $fee];
        }
        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'marketplace.sale',
            'event_id' => "marketplace:{$listingId}",
            'reference' => $buyerReference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Marketplace entitlement transfer',
        ], $entries);
    }

    public function postPosSale(int $organizationId, int $orderId, int $amount, string $paymentMethod, string $reference): Journal
    {
        $accounts = $this->ledger->systemAccounts($organizationId);
        $debit = $paymentMethod === 'wallet' ? $accounts['wallet_liability'] : $accounts['cash_clearing'];
        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'pos.sale',
            'event_id' => "pos:{$orderId}",
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

    public function postWalletSpend(int $organizationId, int $walletId, int $amount, string $reference): Journal
    {
        $accounts = $this->ledger->systemAccounts($organizationId);
        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'wallet.spend',
            'event_id' => "wallet:{$walletId}:spend:{$reference}",
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
        $amount = (int) $payment->amount;
        $commission = intdiv($amount * (int) config('finance.commission_bps', 500), 10000);
        $tenantPayable = $amount - $commission;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => Payment::class, 'source_id' => $payment->id],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $tenantPayable, 'source_type' => Payment::class, 'source_id' => $payment->id],
        ];
        if ($commission > 0) {
            $entries[] = ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => $commission, 'source_type' => Payment::class, 'source_id' => $payment->id];
        }

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'booking.wallet_payment',
            'event_id' => "booking:{$booking->id}:payment:{$payment->id}",
            'reference' => $booking->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Booking payment via wallet balance',
            'metadata' => ['provider' => 'wallet', 'wallet_account_id' => $walletAccountId, 'commission' => $commission],
        ], $entries);
    }

    public function postWalletBookingRefund(Booking $booking, int $amount, int $walletAccountId): Journal
    {
        $organizationId = $booking->court?->venue?->organization_id;
        $commission = intdiv($amount * (int) config('finance.commission_bps', 500), 10000);
        $tenantPayable = $amount - $commission;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'debit', 'amount' => $tenantPayable, 'source_type' => Booking::class, 'source_id' => $booking->id],
            ['account_id' => $accounts['wallet_liability']->id, 'side' => 'credit', 'amount' => $amount, 'source_type' => Booking::class, 'source_id' => $booking->id],
        ];
        if ($commission > 0) {
            $entries[] = ['account_id' => $accounts['platform_commission']->id, 'side' => 'debit', 'amount' => $commission, 'source_type' => Booking::class, 'source_id' => $booking->id];
        }

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => 'booking.wallet_refund',
            'event_id' => "booking:{$booking->id}:refund:" . uniqid(),
            'reference' => $booking->code,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Booking cancellation refund to wallet',
            'metadata' => ['wallet_account_id' => $walletAccountId, 'commission' => $commission],
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
            'currency' => $original->currency,
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
        $commission = intdiv($amount * (int) config('finance.commission_bps', 500), 10000);
        $providerFee = intdiv($amount * (int) config('finance.provider_fee_bps', 0), 10000);
        $tenantPayable = $amount - $commission - $providerFee;
        $accounts = $this->ledger->systemAccounts($organizationId);
        $entries = [
            ['account_id' => $accounts['cash_clearing']->id, 'side' => 'debit', 'amount' => $amount, 'source_type' => $sourceType, 'source_id' => $sourceId],
            ['account_id' => $accounts['tenant_payable']->id, 'side' => 'credit', 'amount' => $tenantPayable, 'source_type' => $sourceType, 'source_id' => $sourceId],
        ];
        if ($commission > 0) {
            $entries[] = ['account_id' => $accounts['platform_commission']->id, 'side' => 'credit', 'amount' => $commission, 'source_type' => $sourceType, 'source_id' => $sourceId];
        }
        if ($providerFee > 0) {
            $entries[] = ['account_id' => $accounts['provider_fee_expense']->id, 'side' => 'credit', 'amount' => $providerFee, 'source_type' => $sourceType, 'source_id' => $sourceId];
        }

        return $this->ledger->post([
            'organization_id' => $organizationId,
            'event_type' => $eventType,
            'event_id' => $eventId,
            'reference' => $reference,
            'currency' => config('finance.currency', 'VND'),
            'description' => 'Gross payment collection',
            'metadata' => $metadata + ['commission' => $commission, 'provider_fee' => $providerFee],
        ], $entries);
    }
}
