{{--
    Payment requests screen when the code is newer than the database (composer update
    without gp247:shop-update): explain the missing step instead of failing on a missing table.

    @aidlc-unit payment-request
    @aidlc-story US-payment-request-admin-screen
--}}
<div>
    <x-gp247::card :title="gp247_language_render('admin.menu_titles.payment_request')">
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800" data-testid="payment-request-not-ready">
            {{ gp247_language_quickly('admin.payment_request.not_ready', 'Payment requests are not set up on this site yet. Run: php artisan gp247:shop-update') }}
        </div>
    </x-gp247::card>
</div>
