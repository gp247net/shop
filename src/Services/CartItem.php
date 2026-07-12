<?php

namespace GP247\Shop\Services;

class CartItem
{
    /**
     * The rowID of the cart item.
     *
     * @var string
     */
    public $rowId;

    /**
     * The ID of the cart item.
     *
     * @var int|string
     */
    public $id;

    /**
     * The quantity for this cart item.
     *
     * @var int|float
     */
    public $qty;

    /**
     * The name of the cart item.
     *
     * @var string
     */
    public $name;

    /**
     * The options for this cart item.
     *
     * @var array
     */
    public $options;

    /**
     * The id store.
     *
     * @var int
     */
    public $storeId;

    /**
     * The FQN of the associated model.
     *
     * WHY: must stay public (not private) — session.serialization = json
     * (compat-foundation_session-json-serialization ADR) round-trips this
     * object through json_encode()/json_decode(), which silently drops
     * non-public properties.
     *
     * @var string|null
     */
    public $associatedModel = null;

    /**
     * CartItem constructor.
     *
     * @param int|string $id
     * @param string     $name
     * @param array      $options
     * @param int        $storeId
     */
    public function __construct($id, $name, array $options = [], $storeId = null)
    {
        $storeId = empty($storeId) ? config('app.storeId') : $storeId;

        if (empty($id)) {
            throw new \InvalidArgumentException('Please supply a valid identifier.');
        }
        if (empty($name)) {
            throw new \InvalidArgumentException('Please supply a valid name.');
        }

        $this->id      = $id;
        $this->name    = $name;
        $this->options = $options;
        $this->rowId   = $this->generateRowId($id, $options);
        $this->storeId = $storeId;
    }

    /**
     * Set the quantity for this cart item.
     *
     * @param int|float $qty
     */
    public function setQuantity($qty)
    {
        if (empty($qty) || ! is_numeric($qty)) {
            throw new \InvalidArgumentException('Please supply a valid quantity.');
        }

        // WHY: reject a fractional qty when the site has not opted into decimal
        // quantities (product_qty_decimal, modification 20260705T093328, ADR-016) —
        // previously this silently accepted any numeric value.
        if (function_exists('gp247_qty_decimal_enabled') && !gp247_qty_decimal_enabled() && (float) $qty != floor((float) $qty)) {
            throw new \InvalidArgumentException('Please supply a whole-number quantity.');
        }

        $this->qty = $qty;
    }


    /**
     * Associate the cart item with the given model.
     *
     * @param mixed $model
     * @return \GP247\Shop\Services\CartItem
     */
    public function associate($model)
    {
        $this->associatedModel = is_string($model) ? $model : get_class($model);
        
        return $this;
    }


    /**
     * Get an attribute from the cart item or get the associated model.
     *
     * @param string $attribute
     * @return mixed
     */
    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->{$attribute};
        }

        if ($attribute === 'model' && isset($this->associatedModel)) {
            return with(new $this->associatedModel)->find($this->id);
        }

        return null;
    }


    /**
     * Create a new instance from the given array.
     *
     * @param array $attributes
     * @return \GP247\Shop\Services\CartItem
     */
    public static function fromArray(array $attributes)
    {
        $options = array_get($attributes, 'options', []);

        return new self($attributes['id'], $attributes['name'], $options, $attributes['storeId']);
    }

    /**
     * Rebuild a CartItem from a plain array/stdClass representation, e.g. one
     * that came back from a session payload decoded with session.serialization
     * = json (which loses the original class). Returns the item unchanged
     * when it is already a CartItem.
     *
     * WHY: session.serialization = json round-trips a CartItem through
     * json_encode()/json_decode(true), turning it into a plain array and
     * dropping its class. Any code reading cart items straight out of the
     * session (not just CartService::getContent()) needs this same
     * rehydration, e.g. ShopCurrency::sumCartCheckout() reading
     * session('dataCheckout').
     *
     * @param array|object $item
     * @return \GP247\Shop\Services\CartItem
     *
     * @aidlc-unit shop-cart
     */
    public static function hydrate($item)
    {
        if ($item instanceof self) {
            return $item;
        }

        $attributes = (array) $item;

        $cartItem = new self(
            $attributes['id'],
            $attributes['name'],
            $attributes['options'] ?? [],
            $attributes['storeId'] ?? null
        );

        if (isset($attributes['qty'])) {
            $cartItem->qty = $attributes['qty'];
        }

        if (!empty($attributes['rowId'])) {
            $cartItem->rowId = $attributes['rowId'];
        }

        if (!empty($attributes['associatedModel'])) {
            $cartItem->associatedModel = $attributes['associatedModel'];
        }

        return $cartItem;
    }

    /**
     * Generate a unique id for the cart item.
     *
     * @param string $id
     * @param array  $options
     * @return string
     */
    protected function generateRowId($id, array $options)
    {
        ksort($options);

        return md5($id . serialize($options));
    }
}
