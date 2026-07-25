<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductAttributeData extends Data
{
    public function __construct(
        public ?string $id = null,
        public ?string $valueId = null,
        public string $name = '',
        public string $value = ''
    ) {}
}
