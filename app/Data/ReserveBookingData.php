<?php

namespace App\Data;

readonly class ReserveBookingData
{
    public function __construct(
        public int $space_id,
        public string $start_date,
        public string $end_date,
        public ?string $notes,
    ) {}

}
