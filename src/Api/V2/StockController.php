<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiStoreScope;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Models\ShopProduct;
use Illuminate\Http\Request;

/**
 * Admin API stock commands for ERP and warehouse tools: receive/issue goods (`delta`),
 * or set the stock after a stock count (`set`, administrator only), one product or a
 * batch of up to 100 lines. Every move is one atomic UPDATE, never below zero, never
 * touching the sold counter; an Idempotency-Key is required so a retry moves nothing
 * twice. A build product's stock comes from its components and is refused.
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-stock-delta, US-api-admin-write-stock-absolute, US-api-admin-write-stock-batch
 * @aidlc-adr api-admin-write_stock-commands
 */
class StockController extends RootFrontController
{
    /** Most lines in one batch. */
    public const BATCH_LIMIT = 100;

    /**
     * `POST product/{id}/stock` — `{delta}` or `{set}`, exactly one.
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function single(Request $request, $id)
    {
        $command = $request->validate($this->commandRules(''));
        $product = ApiStoreScope::apply(ShopProduct::query())->where('id', (string) $id)->first();
        if ($product === null) {
            return ApiErrorResponder::notFound();
        }
        $result = $this->apply($request, $product, $command);
        if (!$result['ok']) {
            return $result['code'] === 'forbidden'
                ? ApiErrorResponder::forbidden()
                : ApiErrorResponder::respond(422, $result['code'], $result['msg']);
        }

        return response()->json(['data' => ['id' => $product->id, 'sku' => $product->sku, 'stock' => $result['stock']]]);
    }

    /**
     * `POST stock/batch` — `{items: [{id|sku, delta|set}, …]}`, at most 100 lines. Each line
     * has its own result; a bad line never stops the others.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batch(Request $request)
    {
        $request->validate(array_merge([
            'items'       => ['required', 'array', 'min:1', 'max:' . self::BATCH_LIMIT],
            'items.*'     => ['array'],
            'items.*.id'  => ['nullable', 'string', 'max:100', 'required_without:items.*.sku', 'prohibits:items.*.sku'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
        ], $this->commandRules('items.*.')));

        $results = [];
        foreach ($request->input('items') as $line) {
            $results[] = $this->line($request, $line);
        }
        $ok = count(array_filter($results, fn ($r) => $r['ok']));

        return response()->json(['data' => ['results' => $results, 'ok' => $ok, 'failed' => count($results) - $ok]]);
    }

    /**
     * Result of one batch line.
     *
     * @param Request $request
     * @param array   $line Validated line.
     * @return array
     */
    private function line(Request $request, array $line): array
    {
        $ref = isset($line['id']) ? ['id' => (string) $line['id']] : ['sku' => (string) $line['sku']];
        if (isset($line['id'])) {
            $product = ApiStoreScope::apply(ShopProduct::query())->where('id', $ref['id'])->first();
            if ($product === null) {
                return $ref + $this->refused('not_found', 'Not found');
            }
        } else {
            $found = ApiStoreScope::apply(ShopProduct::query())->where('sku', $ref['sku'])->limit(2)->get();
            if ($found->isEmpty()) {
                return $ref + $this->refused('sku_not_found', 'No product has this SKU');
            }
            if ($found->count() > 1) {
                return $ref + $this->refused('sku_ambiguous', 'Several products share this SKU; send the id');
            }
            $product = $found->first();
        }

        return $ref + ['id' => $product->id] + $this->apply($request, $product, $line);
    }

    /**
     * Run one stock command on $product.
     *
     * @param Request     $request
     * @param ShopProduct $product
     * @param array       $command `delta` or `set`.
     * @return array{ok: bool, stock?: int|float, code?: string, msg?: string}
     */
    private function apply(Request $request, ShopProduct $product, array $command): array
    {
        if ((int) $product->kind === GP247_PRODUCT_BUILD) {
            return $this->refused('stock_managed_by_components', 'The stock of a build product comes from its components');
        }
        if (isset($command['set'])) {
            // WHY: overwriting the stock discards every move made since the count —
            // the same weight as the stock field of the product form's administrator.
            if (!$request->user()->isAdministrator()) {
                return $this->refused('forbidden', 'Only an administrator may set the stock outright');
            }
            ShopProduct::setStock($product->id, (float) $command['set']);
        } elseif (!ShopProduct::adjustStock($product->id, (float) $command['delta'])) {
            return $this->refused('stock_would_be_negative', 'Not enough stock to issue this quantity');
        }

        return ['ok' => true, 'stock' => $this->number(ShopProduct::where('id', $product->id)->value('stock'))];
    }

    /**
     * Rules of a stock command under $prefix (`` or `items.*.`): exactly one of delta / set,
     * whole numbers unless decimal quantities are on.
     *
     * @param string $prefix
     * @return array<string, array>
     */
    private function commandRules(string $prefix): array
    {
        $type = gp247_qty_decimal_enabled() ? 'numeric' : 'integer';

        return [
            $prefix . 'delta' => ['nullable', $type, 'not_in:0', 'between:-999999999,999999999', 'required_without:' . $prefix . 'set', 'prohibits:' . $prefix . 'set'],
            $prefix . 'set'   => ['nullable', $type, 'min:0', 'max:999999999'],
        ];
    }

    /**
     * @param string $code
     * @param string $msg
     * @return array
     */
    private function refused(string $code, string $msg): array
    {
        return ['ok' => false, 'code' => $code, 'msg' => $msg];
    }

    /**
     * Stock as a JSON number (`15`, not `"15.00"`).
     *
     * @param mixed $value
     * @return int|float
     */
    private function number($value)
    {
        $v = (float) $value;

        return floor($v) === $v ? (int) $v : $v;
    }
}
