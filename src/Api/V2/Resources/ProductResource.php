<?php

namespace GP247\Shop\Api\V2\Resources;

use GP247\Core\Api\V2\ApiQuery;
use GP247\Core\Api\V2\ApiShapeSelection;
use GP247\Shop\Api\Support\Commerce\ProductPurchaseData;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Product in REST API v2. Public view: catalog fields, the description of the
 * requested language and the price the storefront charges (price_final). Admin view
 * adds inventory, cost and moderation fields. New table columns never appear here
 * until listed on purpose. Fields an app needs to sell the product (attributes,
 * image_sizes, stock_status) are added by api-commerce — added only, never changed.
 * With fields[product] chosen, fields left out are not computed (their relations may
 * not be loaded).
 *
 * @aidlc-unit compat-foundation
 * @aidlc-story US-CMP-api-v2-contract, US-CMP-api-v2-query-parameters
 * @aidlc-story US-api-commerce-product-purchase-data, US-CMP-api-sparse-fieldsets
 * @aidlc-adr compat-foundation_rest-api-hardening, compat-foundation_api-http-caching-and-shaping
 */
class ProductResource extends JsonResource
{
    /**
     * @param \GP247\Shop\Models\ShopProduct $resource
     * @param string                         $lang  Language of name/description.
     * @param bool                           $admin  Include admin-only fields.
     * @param bool                           $detail Include the HTML body (content); lists leave it out.
     * @param ApiShapeSelection|null         $shape  Fields chosen by the request (null = every field).
     */
    public function __construct($resource, private string $lang, private bool $admin = false, private bool $detail = false, private ?ApiShapeSelection $shape = null)
    {
        parent::__construct($resource);
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $p = $this->resource;
        $text = $this->detail ? ['name', 'keyword', 'description', 'content'] : ['name', 'keyword', 'description'];
        $described = null;
        $values = ['id' => $p->id, 'sku' => $p->sku, 'alias' => $p->alias];
        foreach ($text as $field) {
            // WHY lazy: descriptions are not loaded when no text field was chosen.
            $values[$field] = function () use ($p, $text, $field, &$described) {
                $described ??= ApiQuery::described($p->descriptions, $this->lang, $text);
                return $described[$field];
            };
        }
        $values += [
            'image'          => $p->image,
            'images'         => fn () => $p->relationLoaded('images') ? $p->images->pluck('image')->values() : [],
            // WHY: a store that hides prices on its storefront hides them here too (admins always see them).
            'price'          => fn () => $this->showsPrice() ? (float) $p->price : null,
            'price_final'    => fn () => $this->showsPrice() ? (float) $p->getFinalPrice() : null,
            'tax_id'         => $p->tax_id,
            'kind'           => (int) $p->kind,
            'product_type'   => $p->product_type,
            'brand_id'       => $p->brand_id,
            'category_ids'   => fn () => $p->relationLoaded('categories') ? $p->categories->pluck('id')->values() : [],
            'minimum'        => (float) $p->minimum,
            'weight'         => (float) $p->weight,
            'weight_class'   => $p->weight_class,
            'length'         => (float) $p->length,
            'width'          => (float) $p->width,
            'height'         => (float) $p->height,
            'length_class'   => $p->length_class,
            'date_available' => $p->date_available,
            'store_id'       => $p->store_id,
            'created_at'     => optional($p->created_at)->toIso8601String(),
            'updated_at'     => optional($p->updated_at)->toIso8601String(),
            'image_sizes'    => fn () => ProductPurchaseData::imageSizes($p->image),
            'stock_status'   => fn () => ProductPurchaseData::stockStatus($p),
            'attributes'     => fn () => ProductPurchaseData::attributes($p, $this->showsPrice()),
        ];

        if ($this->admin) {
            $values += [
                'cost'        => (float) $p->cost,
                'stock'       => (float) $p->stock,
                'sold'        => (float) $p->sold,
                'status'      => (int) $p->status,
                'approve'     => (int) $p->approve,
                'supplier_id' => $p->supplier_id,
                'view'        => (int) $p->view,
            ];
        }

        return ApiShapeSelection::build($this->shape, $values);
    }

    /**
     * Whether prices may be shown (store setting product_price; always for admins).
     *
     * @return bool
     */
    private function showsPrice(): bool
    {
        return $this->admin || (bool) gp247_config('product_price', config('app.storeId'));
    }
}
