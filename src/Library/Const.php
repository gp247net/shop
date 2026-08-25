<?php

//Product kind
define('GP247_PRODUCT_SINGLE', 0);
define('GP247_PRODUCT_BUILD', 1);
define('GP247_PRODUCT_GROUP', 2);

// Product delivery type (formerly "tag"): physical shipment, downloadable file, or
// digital/service. Renamed from GP247_TAG_* to disambiguate from the new keyword-tag
// feature (gp247_shop_product_tag) which only shares the word "tag".
// @aidlc-unit compat-foundation
// @aidlc-story US-CMP-product-type-rename
// @aidlc-adr compat-foundation_product-type-rename
define('GP247_PRODUCT_TYPE_PHYSICAL', 'physical');
define('GP247_PRODUCT_TYPE_DOWNLOAD', 'download');
define('GP247_PRODUCT_TYPE_DIGITAL', 'digital');

// Backward-compatible aliases: external plugins/templates may still reference the old
// GP247_TAG_* names. They resolve to the exact same values, so old code keeps working.
// WHY guarded by !defined: constants are global; avoid redefining if a host app already set them.
if (!defined('GP247_TAG_PHYSICAL')) {
    define('GP247_TAG_PHYSICAL', GP247_PRODUCT_TYPE_PHYSICAL);
}
if (!defined('GP247_TAG_DOWNLOAD')) {
    define('GP247_TAG_DOWNLOAD', GP247_PRODUCT_TYPE_DOWNLOAD);
}
if (!defined('GP247_TAG_DIGITAL')) {
    define('GP247_TAG_DIGITAL', GP247_PRODUCT_TYPE_DIGITAL);
}
