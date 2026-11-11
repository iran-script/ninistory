<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REQUIRES_RECONCILIATION = 'requires_reconciliation';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}
