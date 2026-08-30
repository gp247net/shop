{{--
    Order change history (group E, US-SADM-003): an immutable audit timeline,
    newest first.

    Each line leads with WHO made the change, not with a raw timestamp. An audit
    entry that opens with `2026-08-29T10:03:00.000000Z` tells the reader the one
    thing they can already guess and hides the one thing they came for; the actor
    id was always recorded, it was simply never resolved to a name. The moment is
    still there, in a form a person reads at a glance.

    Content is backend-authored markup from the change log; rendered with {!! !!}
    as the legacy screen does (the source is the backend, not user input).
    Variables: $history (each row carries an `actor` label from OrderManager).

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-003
    @aidlc-adr ADR-006, ADR-007
--}}
<x-gp247::card :title="gp247_language_render('order.history')">
    @if (empty($history))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.no_records') }}</p>
    @else
        <ol class="space-y-3">
            @foreach ($history as $h)
                <li class="rounded-lg border border-gray-200 p-3 dark:border-gray-700" wire:key="history-{{ $h['id'] }}">
                    <div class="mb-1 flex items-baseline justify-between gap-2">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-200" data-testid="shop-admin-order-history-actor">
                            {{ $h['actor'] ?? '' }}
                        </span>
                        <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500" data-testid="shop-admin-order-history-date">
                            {{ gp247_datetime_to_date($h['add_date'] ?? '', 'd/m/Y H:i') }}
                        </span>
                    </div>
                    <div class="text-sm text-gray-700 dark:text-gray-200">{!! $h['content'] ?? '' !!}</div>
                </li>
            @endforeach
        </ol>
    @endif
</x-gp247::card>
