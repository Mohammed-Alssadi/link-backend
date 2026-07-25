<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductCustomOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $type,
        public string $label,
        public bool $isRequired = false,
        /** @var array<array{id: string, label: string}> */
        public array $choices = []
    ) {}
}
