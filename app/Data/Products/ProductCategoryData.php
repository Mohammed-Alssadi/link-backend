<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductCategoryData extends Data
{
    public function __construct(
        public string $id,
        public string $name
    ) {}
}
