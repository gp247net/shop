<?php

namespace GP247\Shop\Api\V2;

use GP247\Core\Api\Support\ApiErrorResponder;
use GP247\Core\Api\Support\ApiStoreScope;
use GP247\Core\Api\V2\ApiQuery;
use GP247\Front\Controllers\RootFrontController;
use GP247\Shop\Api\V2\Resources\ShopResources;
use GP247\Shop\Models\ShopOrder;
use GP247\Shop\Models\ShopOrderStatus;
use GP247\Shop\Models\ShopShippingStatus;
use GP247\Shop\Shipping\ShipmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin API writes on orders (status, shipping status + tracking, notes) for ERP and
 * warehouse tools. Thin: validate, then call the same seams the admin order screen
 * uses, so stock, history and hooks behave exactly as there. The account's store only;
 * permission = write on the Orders screen (api.admin:admin_order.index,write).
 *
 * @aidlc-unit api-admin-write
 * @aidlc-story US-api-admin-write-order-status, US-api-admin-write-order-shipping, US-api-admin-write-order-note
 * @aidlc-adr api-admin-write_write-authorization-as-admin-action
 */
class OrderWriteController extends RootFrontController
{
    /** Label added to history lines written through the API. */
    private const SOURCE = 'API';

    /**
     * `POST order/{id}/status` — through ShopOrder::changeStatus (no lock check: the
     * admin screen reopens finished orders too).
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request, $id)
    {
        $order = $this->order($id);
        if ($order === null) {
            return ApiErrorResponder::notFound();
        }
        $data = $request->validate([
            'status' => ['required', 'integer', Rule::in(array_map('intval', array_keys(ShopOrderStatus::getIdAll())))],
        ]);
        $old = (int) $order->status;
        $refused = $order->changeStatus((int) $data['status'], [
            'admin_id' => $request->user()->id,
            'content'  => 'Change <b>status</b> from \'' . $old . '\' to \'' . (int) $data['status'] . '\' (' . self::SOURCE . ')',
        ]);
        if ($refused !== null) {
            return ApiErrorResponder::respond(409, $refused, gp247_language_render($refused));
        }

        return $this->answer($order->id);
    }

    /**
     * `POST order/{id}/shipping` — shipping status through ShopOrder::changeShippingStatus,
     * tracking code as the order's manual shipment. The payment status is never written
     * here (it follows the order's payments).
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function shipping(Request $request, $id)
    {
        $order = $this->order($id);
        if ($order === null) {
            return ApiErrorResponder::notFound();
        }
        $data = $request->validate([
            'shipping_status' => ['nullable', 'integer', 'required_without_all:tracking_number,tracking_url', Rule::in(array_map('intval', array_keys(ShopShippingStatus::getIdAll())))],
            'tracking_number' => ['nullable', 'string', 'max:100', 'required_with:tracking_url'],
            'tracking_url'    => ['nullable', 'string', 'max:500', 'url:http,https'],
            'payment_status'  => ['prohibited'],
        ]);
        $adminId = (string) $request->user()->id;
        if (isset($data['shipping_status'])) {
            $old = (int) $order->shipping_status;
            $refused = $order->changeShippingStatus((int) $data['shipping_status'], [
                'admin_id' => $adminId,
                'content'  => 'Change <b>shipping_status</b> from \'' . $old . '\' to \'' . (int) $data['shipping_status'] . '\' (' . self::SOURCE . ')',
            ]);
            if ($refused !== null) {
                return ApiErrorResponder::respond(409, $refused, gp247_language_render($refused));
            }
        }
        if (!empty($data['tracking_number'])) {
            app(ShipmentService::class)->recordManual($order, $data['tracking_number'], $data['tracking_url'] ?? null, $adminId);
        }

        return $this->answer($order->id);
    }

    /**
     * `POST order/{id}/notes` — a note in the order history (escaped by the seam).
     *
     * @param Request $request
     * @param string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function notes(Request $request, $id)
    {
        $order = $this->order($id);
        if ($order === null) {
            return ApiErrorResponder::notFound();
        }
        $data = $request->validate(['content' => ['required', 'string', 'max:1000']]);
        $order->addNote($data['content'], ['admin_id' => $request->user()->id, 'source' => self::SOURCE]);

        return $this->answer($order->id);
    }

    /**
     * Order $id in the account's store, or null.
     *
     * @param string $id
     * @return ShopOrder|null
     */
    private function order($id): ?ShopOrder
    {
        return ApiStoreScope::apply(ShopOrder::query())->where('id', (string) $id)->first();
    }

    /**
     * The order as `order/detail` answers it, after the write.
     *
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    private function answer(string $id)
    {
        return ApiQuery::one(ShopOrder::with(['details', 'orderTotal'])->where('id', $id)->first(),
            fn ($o) => ShopResources::order($o, true));
    }
}
