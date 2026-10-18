<?php

namespace GP247\Shop\Shipping\Data;

/**
 * Everything a carrier needs to quote: where from, where to, what (grams), worth how
 * much, and how much to collect on delivery.
 *
 * hash() identifies the request: a cached quote or a rate snapshot is only valid for
 * the request it was made for (US-shop-shipping-rate-snapshot).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 * @aidlc-adr shop-shipping_rate-snapshot-and-currency
 */
final readonly class RateRequest
{
    /**
     * @param string             $storeId       Store whose provider config applies.
     * @param ShippingAddress    $origin        Pick-up address.
     * @param ShippingAddress    $destination   Recipient address.
     * @param array<int, ShippingItem> $items   Parcel lines.
     * @param Money              $declaredValue Value of the goods.
     * @param Money|null         $codAmount     Amount to collect on delivery (null = none).
     * @param string             $paymentMethod Payment plugin key chosen so far ('' = not yet).
     */
    public function __construct(
        public string $storeId,
        public ShippingAddress $origin,
        public ShippingAddress $destination,
        public array $items,
        public Money $declaredValue,
        public ?Money $codAmount = null,
        public string $paymentMethod = '',
    ) {
    }

    /**
     * @return int Total parcel weight in grams.
     */
    public function totalWeightGrams(): int
    {
        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item->lineWeightGrams();
        }

        return $sum;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'origin' => $this->origin->toArray(),
            'destination' => $this->destination->toArray(),
            'items' => array_map(static fn (ShippingItem $i): array => $i->toArray(), $this->items),
            'declared_value' => $this->declaredValue->toArray(),
            'cod_amount' => $this->codAmount?->toArray(),
            'payment_method' => $this->paymentMethod,
        ];
    }

    /**
     * @param array<string, mixed> $data Output of toArray().
     * @return self|null Null when the shape is not a request.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data) || !isset($data['origin'], $data['destination'], $data['items']) || !is_array($data['items'])) {
            return null;
        }

        return new self(
            storeId: (string) ($data['store_id'] ?? ''),
            origin: ShippingAddress::fromArray((array) $data['origin']),
            destination: ShippingAddress::fromArray((array) $data['destination']),
            items: array_map(static fn ($i): ShippingItem => ShippingItem::fromArray((array) $i), array_values($data['items'])),
            declaredValue: Money::fromArray($data['declared_value'] ?? null) ?? new Money('0', 'XXX'),
            codAmount: Money::fromArray($data['cod_amount'] ?? null),
            paymentMethod: (string) ($data['payment_method'] ?? ''),
        );
    }

    /**
     * Stable fingerprint of the request (key order and whitespace insensitive).
     *
     * @return string sha256 hex digest.
     */
    public function hash(): string
    {
        // WHY without payment method / COD: v1 quotes do not depend on how the order is
        // paid, and the method is chosen AFTER the shipping step — keeping it in the hash
        // would invalidate every chosen rate at billing time.
        $data = $this->toArray();
        unset($data['payment_method'], $data['cod_amount']);
        self::ksortRecursive($data);

        return hash('sha256', (string) json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<mixed> $data Array sorted in place (keys at every level).
     * @return void
     */
    private static function ksortRecursive(array &$data): void
    {
        ksort($data);
        foreach ($data as &$value) {
            if (is_array($value)) {
                self::ksortRecursive($value);
            }
        }
    }
}
