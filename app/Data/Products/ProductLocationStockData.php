<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductLocationStockData extends Data
{
    public function __construct(
        public string $locationId,
        public string $locationName,
        public int $quantity = 0,
        public bool $isUnlimited = false
    ) {}
}
