<?php

namespace App\Data;

use Carbon\Carbon;

readonly class ReserveBookingData
{
    public function __construct(
        public int $space_id,
        public Carbon $start_time,
        public Carbon $end_time,
        public ?string $notes,
    ) {}

}
