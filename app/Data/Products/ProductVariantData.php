<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductVariantData extends Data
{
    public function __construct(
        public string $id,
        public string $sku = '',
        public ?string $barcode = null,
        public ?string $mpn = null,
        public ?string $gtin = null,
        public float $price = 0.0,
        public ?float $salePrice = null,
        public ?float $costPrice = null,
        public int $quantity = 0,
        public bool $isUnlimited = false,
        public ?float $weight = null,
        public string $displayName = '',
        public ?string $formattedPrice = null,
        public ?string $formattedSalePrice = null,
        /** @var ProductAttributeData[] */
        public array $attributes = [],
        /** @var ProductLocationStockData[] */
        public array $stocks = [],
        public ?string $locationId = null
    ) {}
}
