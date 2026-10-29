<?php

namespace GP247\Shop\Api\Support\Commerce;

use GP247\Shop\Services\Cart\ArrayCartStore;
use GP247\Shop\Services\CartItem;
use GP247\Shop\Services\CartService;
use Illuminate\Support\Facades\DB;

/**
 * Cart rows of the REST API in shop_shoppingcart, by cart owner: a customer's row is the
 * one the website writes (identifier = customer id), a guest's row is keyed by the hash
 * of its cart token. Writes are compare-and-swap on the content that was read, guest rows
 * expire after 30 days without a change and are cleaned up lazily (no cron).
 *
 * @aidlc-unit api-commerce
 * @aidlc-story US-api-commerce-cart-view
 * @aidlc-story US-api-commerce-guest-cart-token
 * @aidlc-story US-api-commerce-cart-merge-on-login
 * @aidlc-adr api-commerce_request-scoped-checkout-session
 * @aidlc-adr api-commerce_guest-access-tokens
 */
class ApiCartRepository
{
    public const GUEST_TTL_DAYS = 30;
    public const DEFAULT_MAX_LINES = 100;
    public const SWEEP_BATCH = 50;

    /**
     * Most lines a cart may hold (config `gp247-config.shop.api_cart_max_lines`, default 100).
     *
     * @return int
     */
    public static function maxLines(): int
    {
        return max(1, (int) config('gp247-config.shop.api_cart_max_lines', self::DEFAULT_MAX_LINES));
    }

    /**
     * Content of the owner's row, or null when there is none (or a guest row has expired —
     * it is removed then).
     *
     * @param CartOwner $owner
     * @param string    $instance
     * @return string|null
     */
    public function read(CartOwner $owner, string $instance): ?string
    {
        $row = $this->rows($owner, $instance)->first(['content', 'updated_at']);
        if (!$row) {
            return null;
        }
        if ($owner->isGuest() && $row->updated_at !== null && $row->updated_at < $this->expiredBefore()) {
            $this->rows($owner, $instance)->delete();
            return null;
        }

        return (string) $row->content;
    }

    /**
     * Write the owner's row only if it still holds what was read.
     *
     * @param CartOwner   $owner
     * @param string      $instance
     * @param string      $content  New content.
     * @param string|null $expected Content that was read (null = there was no row).
     * @return bool False when the row changed since it was read.
     */
    public function write(CartOwner $owner, string $instance, string $content, ?string $expected): bool
    {
        $now = now();
        if ($expected === null) {
            if ($this->rows($owner, $instance)->exists()) {
                return false;
            }
            $this->table()->insert([
                'identifier' => $owner->storageKey(), 'instance' => $instance, 'content' => $content,
                'store_id' => (string) config('app.storeId'), 'created_at' => $now, 'updated_at' => $now,
            ]);
            return true;
        }

        return $this->rows($owner, $instance)->where('content', $expected)
            ->update(['content' => $content, 'updated_at' => $now]) > 0;
    }

    /**
     * Remove every row (all instances) of the owner.
     *
     * @param CartOwner $owner
     * @return void
     */
    public function forgetOwner(CartOwner $owner): void
    {
        $this->table()->where('identifier', $owner->storageKey())->where('store_id', (string) config('app.storeId'))->delete();
    }

    /**
     * Remove up to $limit expired guest rows (lazy clean-up when a new guest cart is created).
     *
     * @param int $limit
     * @return int Rows removed.
     */
    public function sweepExpiredGuests(int $limit = self::SWEEP_BATCH): int
    {
        return $this->table()->where('identifier', 'like', 'guest:%')->where('updated_at', '<', $this->expiredBefore())
            ->limit($limit)->delete();
    }

    /**
     * Merge the guest cart of $cartToken into the customer's cart with the website's
     * sign-in rule (CartService::syncCartAfterLogin), cap it at the line limit, and remove
     * the guest cart.
     *
     * @param mixed  $customer  Customer model.
     * @param string $cartToken Raw cart token sent at sign-in.
     * @return array{merged: int, dropped: int}|null Null when there was no guest cart.
     */
    public function mergeGuestInto($customer, string $cartToken): ?array
    {
        if (!GuestCartToken::isWellFormed($cartToken)) {
            return null;
        }
        $guest = CartOwner::guest($cartToken);
        $content = $this->read($guest, CartService::DEFAULT_INSTANCE);
        if ($content === null) {
            return null;
        }
        $guestLines = self::decode($content);

        $store = new ArrayCartStore();
        $store->put('cart.' . CartService::DEFAULT_INSTANCE, $guestLines);
        $cart = (new CartService($store))->instance(CartService::DEFAULT_INSTANCE);
        $cart->syncCartAfterLogin($customer->id);

        $dropped = 0;
        $merged = $store->get('cart.' . CartService::DEFAULT_INSTANCE);
        if ($merged !== null && count($merged) > self::maxLines()) {
            $dropped = count($merged) - self::maxLines();
            $store->put('cart.' . CartService::DEFAULT_INSTANCE, collect($merged)->take(self::maxLines()));
            DB::connection(GP247_DB_CONNECTION)->transaction(function () use ($cart, $customer) {
                $cart->removeDatabase($customer->id);
                $cart->saveDatabase($customer->id);
            });
        }
        $this->forgetOwner($guest);

        return ['merged' => $guestLines->count(), 'dropped' => $dropped];
    }

    /**
     * Cart lines (CartItem keyed by row id) from stored JSON.
     *
     * @param string $content
     * @return \Illuminate\Support\Collection
     */
    public static function decode(string $content)
    {
        $lines = json_decode($content, true);

        return collect(is_array($lines) ? $lines : [])->map(fn ($line) => CartItem::hydrate($line));
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function table()
    {
        return DB::connection(GP247_DB_CONNECTION)->table(GP247_DB_PREFIX . 'shop_shoppingcart');
    }

    /**
     * @param CartOwner $owner
     * @param string    $instance
     * @return \Illuminate\Database\Query\Builder
     */
    private function rows(CartOwner $owner, string $instance)
    {
        return $this->table()->where('identifier', $owner->storageKey())->where('instance', $instance)
            ->where('store_id', (string) config('app.storeId'));
    }

    /**
     * @return \Illuminate\Support\Carbon
     */
    private function expiredBefore()
    {
        return now()->subDays(self::GUEST_TTL_DAYS);
    }
}
