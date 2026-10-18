<?php

namespace GP247\Shop\Shipping\Data;

/**
 * One line of the parcel — the product's shipping profile, built by ShippingItemFactory
 * from data GP247 already holds: quantity, weight of one unit (grams), dimensions (cm),
 * value of one unit, and identifying data carriers commonly ask for (SKU, barcode, MPN,
 * variant, brand, category, image). `meta` carries carrier-specific extras added by
 * plugins (shipping_item_resolvers).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 * @aidlc-story US-shop-shipping-product-profile
 * @aidlc-adr shop-shipping_product-shipping-profile
 */
final readonly class ShippingItem
{
    /**
     * @param string               $name        Product name.
     * @param int                  $qty         Quantity (>= 1).
     * @param int                  $weightGrams Weight of ONE unit, in grams.
     * @param Money                $unitValue   Value of one unit.
     * @param string               $sku         Product SKU.
     * @param int                  $lengthCm    Length of one unit (0 = unknown).
     * @param int                  $widthCm     Width of one unit (0 = unknown).
     * @param int                  $heightCm    Height of one unit (0 = unknown).
     * @param string               $productId   Product id.
     * @param string               $variant     Chosen options, human readable ("Màu: Xanh, Size: M").
     * @param string               $barcode     EAN, else UPC, JAN, ISBN.
     * @param string               $mpn         Manufacturer part number.
     * @param string               $brand       Brand name.
     * @param string               $category    First category name.
     * @param string               $imageUrl    Product image URL.
     * @param array<string, mixed> $meta        Carrier-specific extras (no secrets).
     */
    public function __construct(
        public string $name,
        public int $qty,
        public int $weightGrams,
        public Money $unitValue,
        public string $sku = '',
        public int $lengthCm = 0,
        public int $widthCm = 0,
        public int $heightCm = 0,
        public string $productId = '',
        public string $variant = '',
        public string $barcode = '',
        public string $mpn = '',
        public string $brand = '',
        public string $category = '',
        public string $imageUrl = '',
        public array $meta = [],
    ) {
    }

    /**
     * @return int Weight of the whole line in grams.
     */
    public function lineWeightGrams(): int
    {
        return max(0, $this->weightGrams) * max(0, $this->qty);
    }

    /**
     * @param array<string, mixed> $meta Extras merged over the current ones.
     * @return self A copy with the merged meta.
     */
    public function withMeta(array $meta): self
    {
        return $this->with(['meta' => array_merge($this->meta, $meta)]);
    }

    /**
     * @param array<string, mixed> $changes Constructor arguments to replace.
     * @return self A modified copy (readonly value object).
     */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'qty' => $this->qty,
            'weight_grams' => $this->weightGrams,
            'unit_value' => $this->unitValue->toArray(),
            'sku' => $this->sku,
            'length_cm' => $this->lengthCm,
            'width_cm' => $this->widthCm,
            'height_cm' => $this->heightCm,
            'product_id' => $this->productId,
            'variant' => $this->variant,
            'barcode' => $this->barcode,
            'mpn' => $this->mpn,
            'brand' => $this->brand,
            'category' => $this->category,
            'image_url' => $this->imageUrl,
            'meta' => $this->meta,
        ];
    }

    /**
     * @param array<string, mixed> $data Output of toArray().
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $s = static fn (string $key): string => (string) ($data[$key] ?? '');

        return new self(
            name: $s('name'),
            qty: (int) ($data['qty'] ?? 1),
            weightGrams: (int) ($data['weight_grams'] ?? 0),
            unitValue: Money::fromArray($data['unit_value'] ?? null) ?? new Money('0', 'XXX'),
            sku: $s('sku'),
            lengthCm: (int) ($data['length_cm'] ?? 0),
            widthCm: (int) ($data['width_cm'] ?? 0),
            heightCm: (int) ($data['height_cm'] ?? 0),
            productId: $s('product_id'),
            variant: $s('variant'),
            barcode: $s('barcode'),
            mpn: $s('mpn'),
            brand: $s('brand'),
            category: $s('category'),
            imageUrl: $s('image_url'),
            meta: is_array($data['meta'] ?? null) ? $data['meta'] : [],
        );
    }
}
