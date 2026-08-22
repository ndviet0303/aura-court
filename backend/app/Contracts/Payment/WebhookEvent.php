<?php

namespace App\Contracts\Payment;

final readonly class WebhookEvent
{
    public function __construct(
        public string $provider,
        public string $eventId,
        public string $type,
        public string $status,
        public ?string $reference = null,
        public ?string $providerPaymentId = null,
        public ?int $amount = null,
        public string $currency = 'VND',
        public array $data = [],
        public ?\DateTimeImmutable $occurredAt = null,
    ) {}

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'eventId' => $this->eventId,
            'type' => $this->type,
            'status' => $this->status,
            'reference' => $this->reference,
            'providerPaymentId' => $this->providerPaymentId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'data' => $this->data,
            'occurredAt' => $this->occurredAt?->format(DATE_ATOM),
        ];
    }
}
