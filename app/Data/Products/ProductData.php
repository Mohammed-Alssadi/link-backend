<?php

namespace App\Data\Products;

use Spatie\LaravelData\Data;

class ProductData extends Data
{
    public function __construct(
        public string $id,
        public string $nameAr,
        public ?string $nameEn = null,
        public string $descriptionAr = '',
        public ?string $descriptionEn = null,
        public ?string $shortDescriptionAr = null,
        public ?string $shortDescriptionEn = null,
        public string $sku = '',
        public string $barcode = '',
        public ?string $mpn = null,
        public ?string $gtin = null,
        public float $price = 0.0,
        public ?float $costPrice = null,
        public ?float $salePrice = null,
        public bool $isDiscountActive = false,
        public ?string $discountStart = null,
        public ?string $discountEnd = null,
        public bool $isUnlimited = false,
        public int $quantity = 0,
        public float $weight = 0.0,
        public bool $isPublished = true,
        public ?string $status = 'sale',
        public bool $requiresShipping = true,
        public bool $isTaxable = false,
        /** @var ProductCategoryData[] */
        public array $categories = [],
        /** @var ProductImageData[] */
        public array $images = [],
        /** @var ProductLocationStockData[] */
        public array $stocks = [],
        /** @var ProductVariantData[] */
        public array $variants = [],
        /** @var ProductCustomOptionData[]|null */
        public ?array $customOptions = null,
        public ?int $minOrderQuantity = null,
        public ?int $maxOrderQuantity = null,
        public ?int $maxItemsPerUser = null,
        public string $seoTitleAr = '',
        public ?string $seoTitleEn = null,
        public string $seoDescriptionAr = '',
        public ?string $seoDescriptionEn = null,
        public string $seoSlug = '',
        public array $keywords = [],
        public string $platform = 'salla',
        public string $htmlUrl = '',
        public ?string $weightType = 'kg'
    ) {}

    /**
     * تحويل بيانات سلة إلى DTO الموحد لمنتج سلة
     */
    public static function fromSalla(array $rawProduct): self
    {
        $categories = [];
        if (isset($rawProduct['categories']) && is_array($rawProduct['categories'])) {
            foreach ($rawProduct['categories'] as $c) {
                $cName = is_array($c['name'] ?? null)
                    ? ($c['name']['ar'] ?? $c['name']['en'] ?? (string) $c['id'])
                    : (string) ($c['name'] ?? $c['id']);

                $categories[] = new ProductCategoryData(
                    id: (string) $c['id'],
                    name: $cName
                );
            }
        }

        $images = [];
        if (isset($rawProduct['images']) && is_array($rawProduct['images'])) {
            foreach ($rawProduct['images'] as $idx => $img) {
                $images[] = new ProductImageData(
                    id: (string) ($img['id'] ?? $idx),
                    url: (string) ($img['url'] ?? $img['image_url'] ?? ''),
                    isMain: (bool) ($img['is_main'] ?? ($idx === 0))
                );
            }
        }

        $isProductUnlimited = (bool) ($rawProduct['unlimited_quantity'] ?? false);
        $stocks = [];
        if (isset($rawProduct['branches_quantities']) && is_array($rawProduct['branches_quantities'])) {
            foreach ($rawProduct['branches_quantities'] as $bq) {
                $locId = (string) ($bq['branch_id'] ?? $bq['branch']['id'] ?? $bq['id'] ?? '');
                $locName = (string) ($bq['name'] ?? $bq['branch']['name'] ?? "فرع {$locId}");
                $stocks[] = new ProductLocationStockData(
                    locationId: $locId,
                    locationName: $locName,
                    quantity: (int) ($bq['quantity'] ?? 0),
                    isUnlimited: $isProductUnlimited
                );
            }
        }

        $variants = [];
        if (isset($rawProduct['skus']) && is_array($rawProduct['skus'])) {
            foreach ($rawProduct['skus'] as $skuItem) {
                $skuIsUnlimited = (bool) ($skuItem['unlimited_quantity'] ?? false);
                $attributes = [];

                if (isset($skuItem['related_option_values']) && is_array($skuItem['related_option_values'])) {
                    foreach ($skuItem['related_option_values'] as $valItem) {
                        $optName = self::getLocalizedString($valItem['option_name'] ?? $valItem['option']['name'] ?? $valItem['title'] ?? '');
                        $valName = self::getLocalizedString($valItem['name'] ?? $valItem['display_value'] ?? $valItem['value'] ?? $valItem['label'] ?? '');
                        if ($valName !== '') {
                            $attributes[] = new ProductAttributeData(
                                id: (string) ($valItem['option_id'] ?? $valItem['option']['id'] ?? ''),
                                valueId: (string) ($valItem['id'] ?? $valItem['value_id'] ?? ''),
                                name: $optName,
                                value: $valName
                            );
                        }
                    }
                }

                $skuDisplayName = self::getLocalizedString($skuItem['display_name'] ?? $skuItem['name'] ?? $skuItem['title'] ?? '');
                if (empty($skuDisplayName)) {
                    $skuDisplayName = ! empty($attributes)
                        ? implode(' / ', array_map(fn ($a) => $a->value, $attributes))
                        : (string) ($skuItem['sku'] ?? "متغير {$skuItem['id']}");
                }

                $variants[] = new ProductVariantData(
                    id: (string) $skuItem['id'],
                    sku: (string) ($skuItem['sku'] ?? ''),
                    barcode: isset($skuItem['barcode']) ? (string) $skuItem['barcode'] : null,
                    mpn: isset($skuItem['mpn']) ? (string) $skuItem['mpn'] : null,
                    gtin: isset($skuItem['gtin']) ? (string) $skuItem['gtin'] : null,
                    price: self::extractPrice($skuItem['regular_price'] ?? $skuItem['price']) ?? 0.0,
                    salePrice: self::extractPrice($skuItem['sale_price'] ?? null),
                    costPrice: self::extractPrice($skuItem['cost_price'] ?? null),
                    quantity: (int) ($skuItem['stock_quantity'] ?? 0),
                    isUnlimited: $skuIsUnlimited,
                    weight: isset($skuItem['weight']) ? (float) $skuItem['weight'] : null,
                    displayName: $skuDisplayName,
                    attributes: $attributes,
                    stocks: $stocks
                );
            }
        }

        $basePrice = self::extractPrice($rawProduct['regular_price'] ?? $rawProduct['price']) ?? 0.0;
        $salePrice = self::extractPrice($rawProduct['sale_price'] ?? null);

        $nameAr = is_array($rawProduct['name'] ?? null)
            ? (string) ($rawProduct['name']['ar'] ?? '')
            : (string) ($rawProduct['name'] ?? '');

        $nameEn = is_array($rawProduct['name'] ?? null)
            ? (string) ($rawProduct['name']['en'] ?? '')
            : null;

        $tagsArray = [];
        if (isset($rawProduct['tags']) && is_array($rawProduct['tags'])) {
            foreach ($rawProduct['tags'] as $t) {
                $tagsArray[] = is_array($t) ? ($t['name'] ?? '') : (string) $t;
            }
        }

        return new self(
            id: (string) $rawProduct['id'],
            nameAr: $nameAr,
            nameEn: $nameEn,
            descriptionAr: (string) ($rawProduct['description'] ?? ''),
            sku: (string) ($rawProduct['sku'] ?? ''),
            barcode: (string) ($rawProduct['barcode'] ?? ''),
            mpn: isset($rawProduct['mpn']) ? (string) $rawProduct['mpn'] : null,
            gtin: isset($rawProduct['gtin']) ? (string) $rawProduct['gtin'] : null,
            price: $basePrice,
            costPrice: self::extractPrice($rawProduct['cost_price'] ?? null),
            salePrice: $salePrice,
            isDiscountActive: $salePrice !== null && $salePrice > 0,
            discountStart: self::formatDate($rawProduct['discount_start'] ?? $rawProduct['sale_start'] ?? null),
            discountEnd: self::formatDate($rawProduct['discount_end'] ?? $rawProduct['sale_end'] ?? null),
            isUnlimited: $isProductUnlimited,
            quantity: (int) ($rawProduct['quantity'] ?? 0),
            weight: (float) ($rawProduct['weight'] ?? 0),
            isPublished: ($rawProduct['status'] ?? '') === 'sale',
            status: (string) ($rawProduct['status'] ?? 'sale'),
            requiresShipping: (bool) ($rawProduct['require_shipping'] ?? true),
            isTaxable: (bool) ($rawProduct['with_tax'] ?? false),
            categories: $categories,
            images: $images,
            stocks: $stocks,
            variants: $variants,
            customOptions: null,
            minOrderQuantity: isset($rawProduct['minimum_quantity_per_order']) ? (int) $rawProduct['minimum_quantity_per_order'] : null,
            maxOrderQuantity: isset($rawProduct['maximum_quantity_per_order']) ? (int) $rawProduct['maximum_quantity_per_order'] : null,
            maxItemsPerUser: isset($rawProduct['max_items_per_user']) ? (int) $rawProduct['max_items_per_user'] : null,
            seoTitleAr: (string) ($rawProduct['metadata']['title'] ?? ''),
            seoDescriptionAr: (string) ($rawProduct['metadata']['description'] ?? ''),
            seoSlug: (string) ($rawProduct['short_link_code'] ?? ''),
            keywords: array_filter($tagsArray),
            platform: 'salla',
            htmlUrl: (string) ($rawProduct['urls']['customer'] ?? $rawProduct['url'] ?? ''),
            weightType: (string) ($rawProduct['weight_type'] ?? 'kg')
        );
    }

    /**
     * تحويل بيانات زد إلى DTO الموحد لمنتج زد
     */
    public static function fromZid(array $rawProduct, array $rawImages = [], array $rawCustomOptions = []): self
    {
        $categories = [];
        if (isset($rawProduct['categories']) && is_array($rawProduct['categories'])) {
            foreach ($rawProduct['categories'] as $c) {
                $cName = is_array($c['name'] ?? null)
                    ? ($c['name']['ar'] ?? $c['name']['en'] ?? (string) $c['id'])
                    : (string) ($c['name'] ?? $c['id']);

                $categories[] = new ProductCategoryData(
                    id: (string) $c['id'],
                    name: $cName
                );
            }
        }

        $finalRawImages = ! empty($rawImages) ? $rawImages : ($rawProduct['images'] ?? []);
        $images = [];
        if (is_array($finalRawImages)) {
            foreach ($finalRawImages as $idx => $img) {
                $url = (string) ($img['image']['medium'] ?? $img['image_url'] ?? $img['url'] ?? '');
                $images[] = new ProductImageData(
                    id: (string) ($img['id'] ?? $idx),
                    url: $url,
                    isMain: (bool) ($img['is_main'] ?? ($idx === 0))
                );
            }
        }

        $stocks = [];
        if (isset($rawProduct['stocks']) && is_array($rawProduct['stocks'])) {
            foreach ($rawProduct['stocks'] as $st) {
                $locId = (string) ($st['location']['id'] ?? $st['location'] ?? '');
                $locName = (string) ($st['location']['name']['ar'] ?? $st['location']['name']['en'] ?? "مستودع {$locId}");
                $stocks[] = new ProductLocationStockData(
                    locationId: $locId,
                    locationName: $locName,
                    quantity: (int) ($st['available_quantity'] ?? 0),
                    isUnlimited: (bool) ($st['is_infinite'] ?? false)
                );
            }
        }

        $variants = [];
        if (isset($rawProduct['variants']) && is_array($rawProduct['variants'])) {
            foreach ($rawProduct['variants'] as $v) {
                $attributes = [];
                if (isset($v['attributes']) && is_array($v['attributes'])) {
                    foreach ($v['attributes'] as $a) {
                        $val = is_array($a['value'] ?? null)
                            ? ($a['value']['ar'] ?? $a['value']['en'] ?? '')
                            : (string) ($a['value'] ?? '');

                        $attributes[] = new ProductAttributeData(
                            id: (string) ($a['attribute_id'] ?? $a['id'] ?? ''),
                            valueId: (string) ($a['id'] ?? ''),
                            name: (string) ($a['name'] ?? $a['slug'] ?? ''),
                            value: $val
                        );
                    }
                }

                $displayName = ! empty($attributes)
                    ? implode(' / ', array_map(fn ($a) => $a->value, $attributes))
                    : (string) ($v['sku'] ?? "متغير {$v['id']}");

                $variants[] = new ProductVariantData(
                    id: (string) $v['id'],
                    sku: (string) ($v['sku'] ?? ''),
                    barcode: isset($v['barcode']) ? (string) $v['barcode'] : null,
                    price: self::extractPrice($v['price'] ?? 0) ?? 0.0,
                    salePrice: self::extractPrice($v['sale_price'] ?? null),
                    costPrice: self::extractPrice($v['cost'] ?? null),
                    quantity: (int) ($v['quantity'] ?? 0),
                    isUnlimited: (bool) ($v['is_infinite'] ?? false),
                    weight: isset($v['weight']['value']) ? (float) $v['weight']['value'] : (isset($v['weight']) ? (float) $v['weight'] : null),
                    displayName: $displayName,
                    attributes: $attributes,
                    stocks: $stocks
                );
            }
        }

        $customOptions = [];
        if (is_array($rawCustomOptions)) {
            foreach ($rawCustomOptions as $opt) {
                $choices = [];
                $rawChoices = $opt['choices'] ?? $opt['options'] ?? [];
                if (is_array($rawChoices)) {
                    foreach ($rawChoices as $c) {
                        $choices[] = [
                            'id' => (string) $c['id'],
                            'label' => is_array($c['label'] ?? null) ? ($c['label']['ar'] ?? $c['label']['en'] ?? '') : (string) ($c['label'] ?? $c['value'] ?? ''),
                        ];
                    }
                }

                $customOptions[] = new ProductCustomOptionData(
                    id: (string) $opt['id'],
                    type: (string) ($opt['type'] ?? ''),
                    label: is_array($opt['label'] ?? null) ? ($opt['label']['ar'] ?? $opt['label']['en'] ?? '') : (string) ($opt['label'] ?? ''),
                    isRequired: (bool) ($opt['is_required'] ?? false),
                    choices: $choices
                );
            }
        }

        $salePrice = self::extractPrice($rawProduct['sale_price'] ?? null);

        return new self(
            id: (string) $rawProduct['id'],
            nameAr: is_array($rawProduct['name'] ?? null) ? (string) ($rawProduct['name']['ar'] ?? '') : (string) ($rawProduct['name'] ?? ''),
            nameEn: is_array($rawProduct['name'] ?? null) ? (string) ($rawProduct['name']['en'] ?? '') : null,
            descriptionAr: is_array($rawProduct['description'] ?? null) ? (string) ($rawProduct['description']['ar'] ?? '') : (string) ($rawProduct['description'] ?? ''),
            descriptionEn: is_array($rawProduct['description'] ?? null) ? (string) ($rawProduct['description']['en'] ?? '') : null,
            shortDescriptionAr: is_array($rawProduct['short_description'] ?? null) ? (string) ($rawProduct['short_description']['ar'] ?? '') : (string) ($rawProduct['short_description'] ?? null),
            shortDescriptionEn: is_array($rawProduct['short_description'] ?? null) ? (string) ($rawProduct['short_description']['en'] ?? '') : null,
            sku: (string) ($rawProduct['sku'] ?? ''),
            barcode: (string) ($rawProduct['barcode'] ?? ''),
            price: self::extractPrice($rawProduct['price'] ?? 0) ?? 0.0,
            costPrice: self::extractPrice($rawProduct['cost'] ?? null),
            salePrice: $salePrice,
            isDiscountActive: $salePrice !== null && $salePrice > 0,
            discountStart: self::formatDate($rawProduct['purchase_restrictions']['sale_price_period_start'] ?? $rawProduct['sale_price_start'] ?? null),
            discountEnd: self::formatDate($rawProduct['purchase_restrictions']['sale_price_period_end'] ?? $rawProduct['sale_price_end'] ?? null),
            isUnlimited: (bool) ($rawProduct['is_infinite'] ?? false),
            quantity: (int) ($rawProduct['quantity'] ?? 0),
            weight: (float) ($rawProduct['weight']['value'] ?? $rawProduct['weight'] ?? 0),
            isPublished: (bool) ($rawProduct['is_published'] ?? true),
            requiresShipping: (bool) ($rawProduct['requires_shipping'] ?? true),
            isTaxable: (bool) ($rawProduct['is_taxable'] ?? false),
            categories: $categories,
            images: $images,
            stocks: $stocks,
            variants: $variants,
            customOptions: $customOptions,
            minOrderQuantity: isset($rawProduct['purchase_restrictions']['min_quantity_per_cart']) ? (int) $rawProduct['purchase_restrictions']['min_quantity_per_cart'] : null,
            maxOrderQuantity: isset($rawProduct['purchase_restrictions']['max_quantity_per_cart']) ? (int) $rawProduct['purchase_restrictions']['max_quantity_per_cart'] : null,
            seoTitleAr: (string) ($rawProduct['seo']['title']['ar'] ?? ''),
            seoTitleEn: (string) ($rawProduct['seo']['title']['en'] ?? null),
            seoDescriptionAr: (string) ($rawProduct['seo']['description']['ar'] ?? ''),
            seoDescriptionEn: (string) ($rawProduct['seo']['description']['en'] ?? null),
            seoSlug: (string) ($rawProduct['slug'] ?? ''),
            keywords: is_array($rawProduct['keywords'] ?? null) ? $rawProduct['keywords'] : [],
            platform: 'zid',
            htmlUrl: (string) ($rawProduct['html_url'] ?? '')
        );
    }

    private static function extractPrice(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        if (is_array($raw) && isset($raw['amount']) && is_numeric($raw['amount'])) {
            return (float) $raw['amount'];
        }

        return null;
    }

    private static function getLocalizedString(mixed $val): string
    {
        if (! $val) {
            return '';
        }
        if (is_string($val)) {
            return $val;
        }
        if (is_array($val)) {
            return $val['ar'] ?? ($val['en'] ?? (string) reset($val));
        }

        return (string) $val;
    }

    private static function formatDate(mixed $val): ?string
    {
        if (empty($val)) {
            return null;
        }
        $ts = strtotime((string) $val);

        return $ts ? date('Y-m-d', $ts) : null;
    }
}
