<?php

namespace App\Services;

final readonly class PaymentGatewayResult
{
    public function __construct(
        public string $status,
        public ?string $providerReference = null,
        public ?string $authority = null,
        public ?string $redirectUrl = null,
        public ?string $failureCode = null,
        public ?string $failureReason = null,
        public array $metadata = [],
        public ?int $amountToman = null,
    ) {}

    public static function pending(string $reference, ?string $redirectUrl = null, array $metadata = []): self { return new self('pending', $reference, $reference, $redirectUrl, null, null, $metadata); }
    public static function success(string $reference, array $metadata = [], ?int $amountToman = null): self { return new self('succeeded', $reference, $reference, null, null, null, $metadata, $amountToman); }
    public static function failed(string $code, string $reason, array $metadata = []): self { return new self('failed', null, null, null, $code, $reason, $metadata); }
    public static function cancelled(array $metadata = []): self { return new self('cancelled', null, null, null, 'cancelled', 'Payment cancelled by customer.', $metadata); }
}
