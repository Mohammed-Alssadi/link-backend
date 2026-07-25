<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductImageData extends Data
{
    public function __construct(
        public string $id,
        public string $url,
        public bool $isMain = false
    ) {}
}
