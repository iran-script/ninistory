<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;

class InventoryMovementService
{
    public function record(ProductVariant $variant, string $type, int $physicalBefore, int $physicalAfter, int $reservedBefore, int $reservedAfter, string $reason, ?User $actor = null, ?string $reference = null, ?int $orderId = null, ?int $reservationId = null, ?string $idempotencyKey = null): InventoryMovement
    {
        return $variant->inventoryMovements()->create([
            'user_id' => $actor?->id,
            'quantity_delta' => $physicalAfter - $physicalBefore,
            'quantity_before' => $physicalBefore,
            'quantity_after' => $physicalAfter,
            'reason' => mb_substr($reason, 0, 40),
            'reference' => $reference,
            'movement_type' => $type,
            'physical_before' => $physicalBefore,
            'physical_after' => $physicalAfter,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedAfter,
            'order_id' => $orderId,
            'reservation_id' => $reservationId,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
