{{--
    Create new order — TailAdmin styled (rule ui-tailadmin P1/P2).
    Controller: AdminOrderController::create() / postCreate().
    Alpine.js handles: customer auto-fill, currency rate lookup, dynamic product rows, totals calc.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
--}}
@extends('gp247-admin::layouts.plain')

@section('main')
@php
    $inputCls = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $labelCls = 'block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1';
@endphp

<div x-data="orderCreate()" class="space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
            {{ gp247_language_render('admin.order.add_new_title') }}
        </h2>
        <a href="{{ gp247_route_admin('admin_order.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
            <i class="fas fa-arrow-left text-xs"></i> {{ gp247_language_render('admin.back') }}
        </a>
    </div>

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <ul class="list-disc space-y-1 pl-5 text-sm text-red-700 dark:text-red-300">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ gp247_route_admin('admin_order.post_create') }}">
        @csrf

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">

            {{-- ── Left column ──────────────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- Customer --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('admin.customer.list') }}
                    </h3>

                    {{-- Select existing customer --}}
                    <div class="mb-4">
                        <label class="{{ $labelCls }}">{{ gp247_language_render('admin.order.select_customer') }}</label>
                        <select x-model="customerId" @change="loadCustomer"
                                class="{{ $inputCls }}">
                            <option value="">— {{ gp247_language_render('admin.order.no_customer') }} —</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}"
                                    {{ old('customer_id') == $user->id ? 'selected' : '' }}>
                                    {{ $user->email }} — {{ $user->first_name }} {{ $user->last_name }}
                                </option>
                            @endforeach
                        </select>
                        <input type="hidden" name="customer_id" :value="customerId">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        {{-- first_name — always shown, always required --}}
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.first_name') }} *</label>
                            <input type="text" name="first_name" x-model="fields.first_name" required class="{{ $inputCls }}">
                            @error('first_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- last_name — config-driven --}}
                        @if (gp247_config_admin('customer_lastname'))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.last_name') }}{{ gp247_config_admin('customer_lastname_required') ? ' *' : '' }}</label>
                            <input type="text" name="last_name" x-model="fields.last_name"
                                {{ gp247_config_admin('customer_lastname_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('last_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- email — config-driven --}}
                        @if (gp247_config_admin('customer_email'))
                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.email') }}{{ gp247_config_admin('customer_email_required') ? ' *' : '' }}</label>
                            <input type="email" name="email" x-model="fields.email" {{ gp247_config_admin('customer_email_required') ? 'required' : '' }} class="{{ $inputCls }}">
                            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- phone — config-driven --}}
                        @if (gp247_config_admin('customer_phone'))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.phone') }}{{ gp247_config_admin('customer_phone_required') ? ' *' : '' }}</label>
                            <input type="text" name="phone" x-model="fields.phone"
                                {{ gp247_config_admin('customer_phone_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('phone')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- country — config-driven --}}
                        @if (gp247_config_admin('customer_country'))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.country') }}{{ gp247_config_admin('customer_country_required') ? ' *' : '' }}</label>
                            <select name="country" x-model="fields.country"
                                {{ gp247_config_admin('customer_country_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                                <option value="">—</option>
                                @foreach ($countries as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('country')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- address1 — config-driven --}}
                        @if (gp247_config_admin('customer_address1'))
                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.address1') }}{{ gp247_config_admin('customer_address1_required') ? ' *' : '' }}</label>
                            <input type="text" name="address1" x-model="fields.address1"
                                {{ gp247_config_admin('customer_address1_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('address1')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- address2 — config-driven --}}
                        @if (gp247_config_admin('customer_address2'))
                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.address2') }}{{ gp247_config_admin('customer_address2_required') ? ' *' : '' }}</label>
                            <input type="text" name="address2" x-model="fields.address2"
                                {{ gp247_config_admin('customer_address2_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('address2')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- address3 — config-driven --}}
                        @if (gp247_config_admin('customer_address3'))
                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.address3') }}{{ gp247_config_admin('customer_address3_required') ? ' *' : '' }}</label>
                            <input type="text" name="address3" x-model="fields.address3"
                                {{ gp247_config_admin('customer_address3_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('address3')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- postcode — config-driven --}}
                        @if (gp247_config_admin('customer_postcode'))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.postcode') }}{{ gp247_config_admin('customer_postcode_required') ? ' *' : '' }}</label>
                            <input type="text" name="postcode" x-model="fields.postcode"
                                {{ gp247_config_admin('customer_postcode_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('postcode')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        {{-- company — config-driven --}}
                        @if (gp247_config_admin('customer_company'))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.company') }}{{ gp247_config_admin('customer_company_required') ? ' *' : '' }}</label>
                            <input type="text" name="company" x-model="fields.company"
                                {{ gp247_config_admin('customer_company_required') ? 'required' : '' }}
                                class="{{ $inputCls }}">
                            @error('company')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('cart.comment') }}</label>
                            <textarea name="comment" rows="2" class="{{ $inputCls }}">{{ old('comment') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Products --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ gp247_language_render('product.title') }}
                        </h3>
                        <button type="button" @click="addProduct"
                            class="inline-flex items-center gap-1 rounded-md bg-green-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-green-700">
                            <i class="fas fa-plus text-[10px]"></i> {{ gp247_language_render('action.add') }}
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(item, idx) in products" :key="idx">
                            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                          x-text="'#' + (idx + 1)"></span>
                                    <button type="button" @click="removeProduct(idx)"
                                        class="rounded p-0.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/30">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ gp247_language_render('product.title') }}
                                        </label>
                                        <select :name="'products[' + idx + '][product_id]'"
                                                x-model="item.product_id"
                                                @change="fillProduct(idx, $event.target)"
                                                class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                            <option value="">— {{ gp247_language_render('action.select') }} —</option>
                                            @foreach ($products as $p)
                                                <option value="{{ $p['id'] }}"
                                                        data-price="{{ $p['price'] }}"
                                                        data-name="{{ $p['name'] }}"
                                                        data-sku="{{ $p['sku'] }}">
                                                    {{ $p['name'] }} — {{ $p['sku'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <input type="hidden" :name="'products[' + idx + '][name]'" :value="item.name">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">SKU</label>
                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-300"
                                             x-text="item.sku || '—'"></div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ gp247_language_render('product.qty') }}
                                        </label>
                                        <input type="number" :name="'products[' + idx + '][qty]'"
                                               x-model.number="item.qty" min="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}" step="{{ gp247_qty_decimal_enabled() ? '0.01' : '1' }}"
                                               @input="recalc"
                                               class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ gp247_language_render('product.price') }}
                                        </label>
                                        <input type="number" :name="'products[' + idx + '][price]'"
                                               x-model.number="item.price" min="0" step="0.01"
                                               @input="recalc"
                                               class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ gp247_language_render('product.tax') }} (%)
                                        </label>
                                        <input type="number" :name="'products[' + idx + '][tax]'"
                                               x-model.number="item.tax" min="0" step="0.01"
                                               @input="recalc"
                                               class="w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs text-gray-500 dark:text-gray-400">
                                            {{ gp247_language_render('order.totals.sub_total') }}
                                        </label>
                                        <div class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-200"
                                             x-text="fmt(item.qty * item.price)"></div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div x-show="products.length === 0"
                             class="rounded-lg border-2 border-dashed border-gray-200 py-8 text-center text-sm text-gray-400 dark:border-gray-700">
                            {{ gp247_language_render('admin.no_records') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── Right column ─────────────────────────────────────────── --}}
            <div class="space-y-5">

                {{-- Order settings --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('admin.order.setting') }}
                    </h3>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('order.status') }} *</label>
                            <select name="status" required class="{{ $inputCls }}">
                                @foreach ($orderStatus as $id => $name)
                                    <option value="{{ $id }}" {{ old('status') == $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('status')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('order.currency') }}</label>
                            @if ($currencies->count() === 1)
                                @php $onlyCur = $currencies->first(); @endphp
                                <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm font-medium text-gray-700 dark:border-gray-600 dark:bg-gray-700/50 dark:text-gray-200">
                                    {{ $onlyCur->code }}
                                </div>
                                <input type="hidden" name="currency" value="{{ $onlyCur->code }}">
                            @else
                                <select name="currency" x-model="currency" @change="updateRate" required class="{{ $inputCls }}">
                                    @foreach ($currencies as $cur)
                                        <option value="{{ $cur->code }}">{{ $cur->code }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="col-span-2">
                            <label class="{{ $labelCls }}">{{ gp247_language_render('order.exchange_rate') }} *</label>
                            <input type="number" name="exchange_rate" x-model="exchangeRate" step="0.000001" min="0.000001" required class="{{ $inputCls }}">
                            @error('exchange_rate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @if (!empty($paymentMethod))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('order.payment_method') }}</label>
                            <select name="payment_method" class="{{ $inputCls }}">
                                <option value="">—</option>
                                @foreach ($paymentMethod as $key => $name)
                                    <option value="{{ $key }}" {{ old('payment_method') == $key ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif

                        @if (!empty($shippingMethod))
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('order.shipping_method') }}</label>
                            <select name="shipping_method" class="{{ $inputCls }}">
                                <option value="">—</option>
                                @foreach ($shippingMethod as $key => $name)
                                    <option value="{{ $key }}" {{ old('shipping_method') == $key ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('shipping_method')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Totals --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ gp247_language_render('order.totals.title') }}
                    </h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.sub_total') }}</span>
                            <span class="font-medium text-gray-800 dark:text-gray-100" x-text="fmt(subtotal)"></span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.tax') }}</span>
                            <span class="font-medium text-gray-800 dark:text-gray-100" x-text="fmt(taxTotal)"></span>
                        </div>
                        <div class="flex items-center gap-3 border-t border-gray-100 pt-3 dark:border-gray-700">
                            <span class="w-28 shrink-0 text-sm text-gray-500 dark:text-gray-400">
                                {{ gp247_language_render('order.totals.shipping') }}
                            </span>
                            <input type="number" name="shipping" x-model.number="shipping" min="0" step="0.01"
                                   @input="recalc"
                                   class="flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-28 shrink-0 text-sm text-gray-500 dark:text-gray-400">
                                {{ gp247_language_render('order.totals.discount') }}
                            </span>
                            <input type="number" name="discount" x-model.number="discount" min="0" step="0.01"
                                   @input="recalc"
                                   class="flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                        <div class="flex items-center justify-between border-t border-gray-200 pt-3 text-sm dark:border-gray-600">
                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ gp247_language_render('order.totals.total') }}</span>
                            <span class="font-bold text-blue-600 dark:text-blue-400" x-text="fmt(total)"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="w-28 shrink-0 text-sm text-gray-500 dark:text-gray-400">
                                {{ gp247_language_render('order.totals.received') }}
                            </span>
                            <input type="number" name="received" x-model.number="received" min="0" step="0.01"
                                   @input="recalc"
                                   class="flex-1 rounded-md border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-500 dark:text-gray-400">{{ gp247_language_render('order.totals.balance') }}</span>
                            <span class="font-medium" :class="balance < 0 ? 'text-red-600' : (balance == 0 ? 'text-green-600' : 'text-gray-800 dark:text-gray-100')"
                                  x-text="fmt(balance)"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Submit bar --}}
        <div class="flex items-center justify-end gap-3 rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <a href="{{ gp247_route_admin('admin_order.index') }}"
               class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                {{ gp247_language_render('admin.cancel') }}
            </a>
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-save text-xs"></i>
                {{ gp247_language_render('admin.submit') }}
            </button>
        </div>
    </form>
</div>

<script>
function orderCreate() {
    const rates = {!! $currenciesRate !!};
    const userInfoUrl = '{{ gp247_route_admin("admin_order.user_info") }}';

    return {
        customerId: {!! json_encode(old('customer_id', '')) !!},
        currency: {!! json_encode(old('currency', $currencies->first()?->code ?? 'USD')) !!},
        exchangeRate: {!! json_encode(old('exchange_rate')) !!},
        fields: {!! json_encode([
            'first_name' => old('first_name', ''),
            'last_name'  => old('last_name',  ''),
            'email'      => old('email',      ''),
            'phone'      => old('phone',      ''),
            'country'    => old('country',    ''),
            'address1'   => old('address1',   ''),
            'address2'   => old('address2',   ''),
            'address3'   => old('address3',   ''),
            'postcode'   => old('postcode',   ''),
            'company'    => old('company',    ''),
        ]) !!},
        products: [],
        shipping: {!! json_encode((float) old('shipping', 0)) !!},
        discount: {!! json_encode((float) old('discount', 0)) !!},
        received: {!! json_encode((float) old('received', 0)) !!},
        subtotal: 0, taxTotal: 0, total: 0, balance: 0,

        init() {
            if (this.exchangeRate === null) { this.updateRate(); }
            this.recalc();
        },

        updateRate() {
            this.exchangeRate = rates[this.currency] ?? 1;
        },

        async loadCustomer() {
            if (!this.customerId) return;
            const res = await fetch(userInfoUrl + '?id=' + this.customerId);
            const u = await res.json();
            if (!u) return;
            this.fields.first_name = u.first_name ?? '';
            this.fields.last_name  = u.last_name  ?? '';
            this.fields.email      = u.email      ?? '';
            this.fields.phone      = u.phone      ?? '';
            this.fields.country    = u.country    ?? '';
            this.fields.address1   = u.address1   ?? '';
            this.fields.address2   = u.address2   ?? '';
            this.fields.address3   = u.address3   ?? '';
            this.fields.postcode   = u.postcode   ?? '';
            this.fields.company    = u.company    ?? '';
        },

        addProduct() {
            this.products.push({ product_id: '', name: '', sku: '', qty: 1, price: 0, tax: 0 });
        },

        removeProduct(idx) {
            this.products.splice(idx, 1);
            this.recalc();
        },

        fillProduct(idx, select) {
            const opt = select.options[select.selectedIndex];
            if (!opt) return;
            this.products[idx].name  = opt.dataset.name  ?? '';
            this.products[idx].sku   = opt.dataset.sku   ?? '';
            this.products[idx].price = parseFloat(opt.dataset.price ?? 0);
            this.recalc();
        },

        recalc() {
            let sub = 0, tax = 0;
            this.products.forEach(p => {
                const lineBase = (p.qty || 0) * (p.price || 0);
                const lineTax  = lineBase * ((p.tax || 0) / 100);
                sub += lineBase;
                tax += lineTax;
            });
            this.subtotal  = sub;
            this.taxTotal  = tax;
            this.total     = sub + tax + (this.shipping || 0) - (this.discount || 0);
            this.balance   = this.total - (this.received || 0);
        },

        fmt(v) { return parseFloat(v || 0).toFixed(2); },

    };
}
</script>
@endsection
