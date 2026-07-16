{{--
    Order status-change history (group E, US-SADM-003): an immutable audit
    timeline (newest first). Content is admin-authored markup from the change
    log; rendered with {!! !!} as the legacy screen does (the source is the
    backend, not user input). Variables: $history.

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
                    <div class="text-xs text-gray-400 dark:text-gray-500">{{ $h['add_date'] ?? '' }}</div>
                    <div class="text-sm text-gray-700 dark:text-gray-200">{!! $h['content'] ?? '' !!}</div>
                </li>
            @endforeach
        </ol>
    @endif
</x-gp247::card>
