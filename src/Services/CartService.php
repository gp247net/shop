<?php

namespace GP247\Shop\Services;

use Closure;
use Illuminate\Support\Collection;
use GP247\Shop\Models\ShopCart;
use Carbon\Carbon;

class CartService
{
    const DEFAULT_INSTANCE = 'default';

    /**
     * Holds the current cart instance.
     *
     * @var string
     */
    private $instance;

    /**
     * Cart constructor.
     *
     */
    public function __construct()
    {
        $this->instance(self::DEFAULT_INSTANCE);
    }

    /**
     * Set the current cart instance.
     *
     * @param string|null $instance
     * @return \GP247\Shop\Services\CartService
     */
    public function instance($instance = null)
    {
        $instance = $instance ?: self::DEFAULT_INSTANCE;
        $instance = ($instance == 'cart') ? self::DEFAULT_INSTANCE : $instance;

        $this->instance = sprintf('%s.%s', 'cart', $instance);

        return $this;
    }

    /**
     * Get the current cart instance.
     *
     * @return string
     */
    public function currentInstance()
    {
        return str_replace('cart.', '', $this->instance);
    }

    /**
     * Add an item to the cart.
     *
     * @param array     $dataCart
     * @return \GP247\Shop\Services\CartItem
     */
    public function add(array $dataCart)
    {
        $cartItem = $this->createCartItem($dataCart);

        $content = $this->getContent();

        if ($content->has($cartItem->rowId)) {
            $cartItem->qty += $content->get($cartItem->rowId)->qty;
        }

        $content->put($cartItem->rowId, $cartItem);

        session([$this->instance => $content]);

        if (customer()->user()) {
            $userId = customer()->user()->id;
            $this->_updateDatabase($userId);
        }

        return $cartItem;
    }

    /**
     * Update the cart item with the given rowId.
     *
     * @param string $rowId
     * @param mixed  $qty
     * @return \GP247\Shop\Services\CartItem
     */
    public function update($rowId, $qty)
    {
        $cartItem = $this->get($rowId);
        if (!$cartItem) {
            return;
        }
        
        $cartItem->qty = $qty;

        $content = $this->getContent();

        if ($rowId !== $cartItem->rowId) {
            $content->pull($rowId);

            if ($content->has($cartItem->rowId)) {
                $existingCartItem = $this->get($cartItem->rowId);
                $cartItem->setQuantity($existingCartItem->qty + $cartItem->qty);
            }
        }

        if ($cartItem->qty <= 0) {
            $this->remove($cartItem->rowId);
            return;
        } else {
            $content->put($cartItem->rowId, $cartItem);
        }

        session([$this->instance => $content]);

        if (customer()->user()) {
            $userId = customer()->user()->id;
            $this->_updateDatabase($userId);
        }

        return $cartItem;
    }

    /**
     * Remove the cart item with the given rowId from the cart.
     *
     * @param string $rowId
     * @return void
     */
    public function remove($rowId)
    {
        $cartItem = $this->get($rowId);

        $content = $this->getContent();

        $content->pull($cartItem->rowId);

        session([$this->instance => $content]);

        if (customer()->user()) {
            $userId = customer()->user()->id;
            $this->_updateDatabase($userId);
        }
    }

    /**
     * Get a cart item from the cart by its rowId.
     *
     * @param string $rowId
     * @return \GP247\Shop\Services\CartItem
     */
    public function get($rowId)
    {
        $content = $this->getContent();

        if (!$content->has($rowId)) {
            return;
        }

        return $content->get($rowId);
    }

    /**
     * Destroy the current cart instance.
     *
     * @return void
     */
    public function destroy()
    {
        session()->forget($this->instance);
        if (customer()->user()) {
            $userId = customer()->user()->id;
            $this->_updateDatabase($userId);
        }
    }

    /**
     * Get the content of the cart.
     *
     * @return \Illuminate\Support\Collection
     */
    public function content()
    {
        if (is_null(session($this->instance))) {
            return new Collection([]);
        }
        //Check products in cart
        $content = session($this->instance);
        foreach ($content as $key => $item) {
            $product = \GP247\Shop\Models\ShopProduct::where('id', $item->id)
                ->where('status', 1) //Active
                ->where('approve', 1) //Approve
                ->first();
            if (!$product) {
                $this->remove($key);
            }
        }
        return session($this->instance);
    }

    /**
     * Get items in cart group by storeId
     *
     * @return  [type]  [return description]
     */
    public function getItemsGroupByStore()
    {
        return $this->content()->groupBy('storeId');
    }

    /**
     * Get the number of items in the cart.
     *
     * @return int|float
     */
    public function count()
    {
        $content = $this->getContent();

        return $content->sum('qty');
    }

    /**
     * Search the cart content for a cart item matching the given search closure.
     *
     * @param \Closure $search
     * @return \Illuminate\Support\Collection
     */
    public function search(Closure $search)
    {
        $content = $this->getContent();

        return $content->filter($search);
    }

    /**
     * Associate the cart item with the given rowId with the given model.
     *
     * @param string $rowId
     * @param mixed  $model
     * @return void
     */
    public function associate($rowId, $model)
    {
        if (is_string($model) && !class_exists($model)) {
            throw new \Exception("The supplied model {$model} does not exist.");
        }

        $cartItem = $this->get($rowId);

        $cartItem->associate($model);

        $content = $this->getContent();

        $content->put($cartItem->rowId, $cartItem);

        session([$this->instance => $content]);
    }

    /**
     * Store an the current instance of the cart.
     *
     * @param mixed $identifier
     * @return void
     */
    public function saveDatabase($identifier)
    {
        $content = $this->getContent();
        $currentInstance = $this->currentInstance();

        if ($this->storedCartWithIdentifierExists($identifier, $currentInstance)) {
            throw new \Exception("A cart with identifier {$identifier}_{$currentInstance} was already stored.");
        }

        $storeId = config('app.storeId');

        ShopCart::insert(
            [
                'identifier' => $identifier,
                'instance' => $currentInstance,
                'content' => $content->toJson(),
                'store_id' => $storeId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );
    }

    /**
     * Restore the cart with the given identifier.
     *
     * @param mixed $identifier
     * @return void
     */
    public function removeDatabase($identifier)
    {
        $currentInstance = $this->currentInstance();
        return (new ShopCart)
            ->where('identifier', $identifier)
            ->where('instance', $currentInstance)
            ->where('store_id', config('app.storeId'))
            ->delete();
    }

    /**
     * Magic method.
     *
     * @param string $attribute
     * @return float|null
     */
    public function __get($attribute)
    {
        return null;
    }

    /**
     * Get the carts content, if there is no cart content set yet, return a new empty Collection
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getContent()
    {
        $content = session()->has($this->instance)
        ? session()->get($this->instance)
        : new Collection;

        return $content;
    }

    /**
     * Create a new CartItem from the supplied attributes.
     *
     * @param array     $dataCart
     * @return \GP247\Shop\Services\CartItem
     */
    private function createCartItem($dataCart)
    {
        $cartItem = CartItem::fromArray($dataCart);
        $cartItem->setQuantity($dataCart['qty']);

        return $cartItem;
    }

    /**
     * @param $identifier
     * @param $instance
     * @return bool
     */
    private function storedCartWithIdentifierExists($identifier, $instance)
    {
        return (new ShopCart)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->where('store_id', config('app.storeId'))
            ->exists();
    }

    /*
    Get list Cart
    */
    public static function getListCart($instance = self::DEFAULT_INSTANCE)
    {
        $cart = (new CartService)->instance($instance);
        $arrCart['count'] = $cart->count();
        $arrCart['items'] = [];
        if ($cart->count()) {
            foreach ($cart->content() as $key => $item) {
                $product = \GP247\Shop\Models\ShopProduct::find($item->id);
                if ($product) {
                    $arrCart['items'][] = [
                        'id'        => $item->id,
                        'rowId'     => $item->rowId,
                        'name'      => $product->getName(),
                        'qty'       => $item->qty,
                        'image'     => gp247_file($product->getThumb()),
                        'price'     => $product->getFinalPrice(),
                        'showPrice' => $product->showPrice(),
                        'url'       => $product->getUrl(),
                        'storeId'   => $item->storeId,
                    ];
                }
            }
        }

        return $arrCart;
    }

    /**
     * Update database cart
     *
     * @param [type] $identifier
     * @return void
     */
    private function _updateDatabase($identifier)
    {
        $this->removeDatabase($identifier);
        $this->saveDatabase($identifier);
    }

    /**
     * Sync cart data after user login
     * 
     * @param int $userId User ID after login
     * @return void
     */
    public function syncCartAfterLogin($userId)
    {
        // Get cart data from database
        $dbCart = ShopCart::where('identifier', $userId)
            ->where('instance', $this->currentInstance())
            ->where('store_id', config('app.storeId'))
            ->first();

        if ($dbCart) {
            // If user has cart in database, merge with current session cart
            $dbContent = collect(json_decode($dbCart->content, true));
            $sessionContent = $this->getContent();

            // Merge products from both carts
            $mergedContent = new Collection();
            
            // Add items from database cart
            foreach ($dbContent as $item) {
                $product = \GP247\Shop\Models\ShopProduct::where('id', $item['id'])
                    ->where('status', 1)
                    ->where('approve', 1)
                    ->first();
                    
                if ($product) {
                    $cartItem = CartItem::fromArray($item);
                    $cartItem->setQuantity($item['qty']);
                    $mergedContent->put($cartItem->rowId, $cartItem);
                }
            }

            // Add or update items from session cart
            foreach ($sessionContent as $item) {
                $product = \GP247\Shop\Models\ShopProduct::where('id', $item->id)
                    ->where('status', 1)
                    ->where('approve', 1)
                    ->first();
                    
                if ($product) {
                    if ($mergedContent->has($item->rowId)) {
                        // If item exists in both carts, add quantities
                        $mergedContent->get($item->rowId)->qty += $item->qty;
                    } else {
                        $mergedContent->put($item->rowId, $item);
                    }
                }
            }

            // Update session with merged content
            session([$this->instance => $mergedContent]);
            
            // Update database
            $this->_updateDatabase($userId);
        } else {
            // If no database cart, just save current session cart
            $this->_updateDatabase($userId);
        }
    }
}
