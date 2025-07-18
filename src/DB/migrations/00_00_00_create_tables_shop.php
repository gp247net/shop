<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //Drop table if exist
        $this->down();
        $schema = Schema::connection(GP247_DB_CONNECTION);

        $schema->create(
            GP247_DB_PREFIX.'shop_brand',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('alias', 120)->index();
                $table->string('image', 255)->nullable();
                $table->string('url', 100)->nullable();
                $table->tinyInteger('status')->default(0);
                $table->integer('sort')->default(0);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_category',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('image', 255)->nullable();
                $table->string('alias', 120)->index();
                $table->uuid('parent')->default(0);
                $table->integer('top')->nullable()->default(0);
                $table->tinyInteger('status')->default(0);
                $table->integer('sort')->default(0);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_category_description',
            function (Blueprint $table) {
                $table->uuid('category_id');
                $table->string('lang', 10)->index();
                $table->string('title', 255)->nullable();
                $table->string('keyword', 200)->nullable();
                $table->string('description', 500)->nullable();
                $table->unique(['category_id', 'lang']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_currency',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->string('code', 10)->unique();
                $table->string('symbol', 10);
                $table->float('exchange_rate');
                $table->tinyInteger('precision')->default(2);
                $table->tinyInteger('symbol_first')->default(0);
                $table->string('thousands')->default(',');
                $table->tinyInteger('status')->default(0);
                $table->integer('sort')->default(0);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_order',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->index();
                $table->string('domain')->nullable();
                $table->decimal('subtotal',15,2)->nullable()->default(0);
                $table->decimal('shipping',15,2)->nullable()->default(0);
                $table->decimal('discount',15,2)->nullable()->default(0);
                $table->integer('payment_status')->default(1);
                $table->integer('shipping_status')->default(1);
                $table->integer('status')->default(0);
                $table->decimal('tax',15,2)->nullable()->default(0);
                $table->decimal('other_fee',15,2)->nullable()->default(0);
                $table->decimal('total',15,2)->nullable()->default(0);
                $table->string('currency', 10);
                $table->decimal('exchange_rate',15,2)->nullable();
                $table->decimal('received',15,2)->nullable()->default(0);
                $table->decimal('balance',15,2)->nullable()->default(0);
                $table->string('first_name', 100);
                $table->string('last_name', 100)->nullable();
                $table->string('first_name_kana', 100)->nullable();
                $table->string('last_name_kana', 100)->nullable();
                $table->string('address1', 100)->nullable();
                $table->string('address2', 100)->nullable();
                $table->string('address3', 100)->nullable();
                $table->string('country', 10)->nullable()->default('VN');
                $table->string('company', 100)->nullable();
                $table->string('postcode', 10)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('email', 150);
                $table->string('comment', 300)->nullable();
                $table->string('payment_method', 100)->nullable();
                $table->string('shipping_method', 100)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->string('device_type', 20)->nullable()->default('other')->index();
                $table->string('ip', 100)->nullable();
                $table->string('transaction', 100)->nullable();
                $table->uuid('store_id')->nullable()->default(1)->index();
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_order_detail',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->uuid('product_id');
                $table->string('name', 255);
                $table->decimal('price',15,2)->default(0);
                $table->integer('qty')->default(0);
                $table->uuid('store_id')->default(1);
                $table->decimal('total_price',15,2)->default(0);
                $table->decimal('tax',15,2)->default(0);
                $table->string('sku', 50);
                $table->string('currency', 10);
                $table->float('exchange_rate')->nullable();
                $table->string('attribute', 100)->nullable();
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_order_history',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id');
                $table->string('content', 300);
                $table->uuid('admin_id')->default(0);
                $table->uuid('customer_id')->default(0);
                $table->integer('order_status_id')->default(0);
                $table->timestamp('add_date', $precision = 0);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_order_status',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_order_total',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('order_id')->index();
                $table->string('title', 255);
                $table->string('code', 100);
                $table->decimal('value',15,2)->default(0);
                $table->string('text', 200)->nullable();
                $table->integer('sort')->default(1);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_payment_status',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('sku', 50)->index();
                $table->string('upc', 20)->nullable()->comment('upc code');
                $table->string('ean', 20)->nullable()->comment('ean code');
                $table->string('jan', 20)->nullable()->comment('jan code');
                $table->string('isbn', 20)->nullable()->comment('isbn code');
                $table->string('mpn', 64)->nullable()->comment('mpn code');
                $table->string('image', 255)->nullable();
                $table->uuid('brand_id')->nullable()->default(0)->index();
                $table->uuid('supplier_id')->nullable()->default(0)->index();
                $table->decimal('price',15,2)->nullable()->default(0);
                $table->decimal('cost',15,2)->nullable()->nullable()->default(0);
                $table->integer('stock')->nullable()->default(0);
                $table->integer('sold')->nullable()->default(0);
                $table->integer('minimum')->nullable()->default(0);
                $table->string('weight_class')->nullable();
                $table->decimal('weight',15,2)->nullable()->default(0);
                $table->string('length_class')->nullable();
                $table->decimal('length',15,2)->nullable()->default(0);
                $table->decimal('width',15,2)->nullable()->default(0);
                $table->decimal('height',15,2)->nullable()->default(0);
                $table->tinyInteger('kind')->nullable()->default(0)->comment('0:single, 1:bundle, 2:group')->index();
                $table->string('tag', 50)->nullable()->default('physical')->index();
                $table->string('tax_id', 50)->nullable()->default(0)->comment('0:No-tax, auto: Use tax default')->index();
                $table->tinyInteger('status')->default(0)->index();
                $table->tinyInteger('approve')->default(1)->index();
                $table->integer('sort')->default(0);
                $table->integer('view')->default(0);
                $table->string('alias', 120)->index();
                $table->timestamp('date_lastview', $precision = 0)->nullable();
                $table->date('date_available')->nullable();
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_description',
            function (Blueprint $table) {
                $table->uuid('product_id');
                $table->string('lang', 10)->index();
                $table->string('name', 255)->nullable();
                $table->string('keyword', 200)->nullable();
                $table->string('description', 500)->nullable();
                $table->mediumText('content')->nullable();
                $table->unique(['product_id', 'lang']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_image',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('image', 255);
                $table->uuid('product_id')->default(0)->index();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_build',
            function (Blueprint $table) {
                $table->uuid('build_id');
                $table->uuid('product_id');
                $table->integer('quantity');
                $table->primary(['build_id', 'product_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_group',
            function (Blueprint $table) {
                $table->uuid('group_id');
                $table->uuid('product_id');
                $table->primary(['group_id', 'product_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_category',
            function (Blueprint $table) {
                $table->uuid('product_id');
                $table->uuid('category_id');
                $table->primary(['product_id', 'category_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_attribute_group',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->tinyInteger('status')->default(0);
                $table->integer('sort')->default(0);
                $table->string('type', 50)->comment('radio,select,checkbox');
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_attribute',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name', 255);
                $table->integer('attribute_group_id');
                $table->uuid('product_id');
                $table->decimal('add_price',15,2)->default(0);
                $table->integer('sort')->default(0);
                $table->tinyInteger('status')->default(1);
                $table->index(['product_id', 'attribute_group_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_shipping_status',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_shoppingcart',
            function (Blueprint $table) {
                $table->string('identifier', 100);
                $table->string('instance', 100);
                $table->text('content');
                $table->timestamps();
                $table->index(['identifier', 'instance']);
                $table->uuid('store_id')->default(1)->index();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_customer',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('first_name', 100);
                $table->string('last_name', 100)->nullable();
                $table->string('first_name_kana', 100)->nullable();
                $table->string('last_name_kana', 100)->nullable();
                $table->string('email', 150)->nullable();
                $table->tinyInteger('sex')->nullable()->comment('0:women, 1:men');
                $table->date('birthday')->nullable();
                $table->string('password', 100)->nullable();
                $table->uuid('address_id')->default(0)->index();
                $table->string('postcode', 10)->nullable();
                $table->string('address1', 100)->nullable();
                $table->string('address2', 100)->nullable();
                $table->string('address3', 100)->nullable();
                $table->string('company', 100)->nullable();
                $table->string('country', 10)->nullable()->default('VN');
                $table->string('phone', 20)->nullable();
                $table->uuid('store_id')->default(1)->index();
                $table->string('remember_token', 100)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->tinyInteger('group')->default(1);
                $table->timestamp('email_verified_at', $precision = 0)->nullable();
                $table->timestamps();
                //Login social
                $table->string('provider', 50)->nullable();
                $table->string('provider_id', 50)->nullable();
                $table->unique(['email', 'provider', 'provider_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_customer_address',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('customer_id')->index();
                $table->string('first_name', 100);
                $table->string('last_name', 100)->nullable();
                $table->string('first_name_kana', 100)->nullable();
                $table->string('last_name_kana', 100)->nullable();
                $table->string('postcode', 10)->nullable();
                $table->string('address1', 100)->nullable();
                $table->string('address2', 100)->nullable();
                $table->string('address3', 100)->nullable();
                $table->string('country', 10)->nullable()->default('VN');
                $table->string('phone', 20)->nullable();
                $table->timestamps();
            }
        );


        $schema->create(
            GP247_DB_PREFIX.'shop_supplier',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 255);
                $table->string('alias', 120)->index();
                $table->string('email', 150)->nullable();
                $table->string('phone', 20)->nullable();
                $table->string('image', 255)->nullable();
                $table->string('address', 100)->nullable();
                $table->string('url', 100)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->uuid('store_id')->default(1)->index();
                $table->integer('sort')->default(0);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_tax',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255);
                $table->integer('value')->default(0);
                $table->timestamps();
            }
        );
        $schema->create(
            GP247_DB_PREFIX.'shop_product_promotion',
            function (Blueprint $table) {
                $table->uuid('product_id')->primary();
                $table->decimal('price_promotion',15,2);
                $table->timestamp('date_start', $precision = 0)->nullable();
                $table->timestamp('date_end', $precision = 0)->nullable();
                $table->integer('status_promotion')->default(1);
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_product_download',
            function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('product_id');
                $table->string('path', 255);
            }
        );
        //Multi store
        $schema->create(
            GP247_DB_PREFIX.'shop_product_store',
            function (Blueprint $table) {
                $table->uuid('product_id');
                $table->uuid('store_id');
                $table->primary(['product_id', 'store_id']);
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_category_store',
            function (Blueprint $table) {
                $table->uuid('category_id');
                $table->uuid('store_id');
                $table->primary(['category_id', 'store_id']);
            }
        );


        $schema->create(
            GP247_DB_PREFIX.'shop_sessions',
            function ($table) {
                $table->string('id', 100)->unique();
                $table->uuid('customer_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->text('payload');
                $table->integer('last_activity');
                $table->timestamps();
            }
        );

        $schema->create(
            GP247_DB_PREFIX.'shop_password_resets',
            function (Blueprint $table) {
                $table->string('email', 150);
                $table->string('token', 255);
                $table->timestamp('created_at', $precision = 0);
                $table->index('email');
            }
        );


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $schema = Schema::connection(GP247_DB_CONNECTION);
        
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_brand');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_category');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_category_description');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_currency');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_order');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_order_detail');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_order_history');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_order_status');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_order_total');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_payment_status');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_description');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_image');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_build');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_attribute');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_attribute_group');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_group');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_category');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_shipping_status');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_shoppingcart');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_promotion');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_customer');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_supplier');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_customer_address');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_password_resets');

        $schema->dropIfExists(GP247_DB_PREFIX.'shop_sessions');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_tax');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_download');
        //Multi store
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_product_store');
        $schema->dropIfExists(GP247_DB_PREFIX.'shop_category_store');
        
    }
};
