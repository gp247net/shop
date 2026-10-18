<?php

namespace GP247\Shop\Shipping\Data;

/**
 * A postal address as the shipping contract carries it — split into the parts
 * carriers ask for, so a provider never has to parse address lines.
 *
 * `province` is the shop's `city` field; `district` is optional because Vietnam
 * moved to a two-level division (province → ward) on 2025-07-01
 * (RISK-TECH-vn-address-reform).
 *
 * @aidlc-unit shop-shipping
 * @aidlc-story US-shop-shipping-checkout-rates
 * @aidlc-story US-CMP-address-ward
 */
final readonly class ShippingAddress
{
    /**
     * @param string $name     Recipient / sender name.
     * @param string $phone    Contact phone.
     * @param string $country  ISO-3166 alpha-2 code.
     * @param string $province Province / state / city (first-level division).
     * @param string $district District (optional second level).
     * @param string $ward     Ward / commune.
     * @param string $line1    Street address.
     * @param string $line2    Extra address line.
     * @param string $postcode Postal code.
     * @param string $email    Contact email.
     * @param string $company  Company name.
     */
    public function __construct(
        public string $name = '',
        public string $phone = '',
        public string $country = '',
        public string $province = '',
        public string $district = '',
        public string $ward = '',
        public string $line1 = '',
        public string $line2 = '',
        public string $postcode = '',
        public string $email = '',
        public string $company = '',
    ) {
    }

    /**
     * Build from the shop's address block (checkout session, order row, origin config).
     *
     * @param array<string, mixed> $block Keys of shop_order / shippingAddress
     *                                    (first_name, last_name, phone, city, district, ward, address1..3, …).
     * @return self
     */
    public static function fromShopBlock(array $block): self
    {
        // WHY decode: the committed checkout address went through gp247_clean() (html-escaped)
        // while the address being typed did not; both must describe the same parcel.
        $s = static fn (string $key): string => trim(htmlspecialchars_decode((string) ($block[$key] ?? ''), ENT_QUOTES));
        $name = trim($s('first_name') . ' ' . $s('last_name'));

        return new self(
            name: $name !== '' ? $name : $s('name'),
            phone: $s('phone'),
            country: strtoupper($s('country')),
            province: $s('city') !== '' ? $s('city') : $s('province'),
            district: $s('district'),
            ward: $s('ward'),
            line1: $s('address1') !== '' ? $s('address1') : $s('line1'),
            line2: trim($s('address2') . ' ' . $s('address3')) !== '' ? trim($s('address2') . ' ' . $s('address3')) : $s('line2'),
            postcode: $s('postcode'),
            email: $s('email'),
            company: $s('company'),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * @param array<string, mixed> $data Output of toArray().
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $args = [];
        foreach (array_keys(get_class_vars(self::class)) as $key) {
            $args[$key] = (string) ($data[$key] ?? '');
        }

        return new self(...$args);
    }
}
