<?php

namespace App\Console\Commands;

use App\Services\InventoryReservationService;
use Illuminate\Console\Command;

class ExpireInventoryReservations extends Command
{
    protected $signature = 'inventory:expire-reservations';
    protected $description = 'Release expired pending-payment inventory reservations';
    public function handle(InventoryReservationService $reservations): int { $this->info('Expired reservations: '.$reservations->expireStale()); return self::SUCCESS; }
}
