{{--
    Currency manager (shop-admin Unit) — two-panel: form (left) + list (right) on
    the core ResourcePanel base (P1). UI text via gp247_language_render.

    @aidlc-unit shop-admin
    @aidlc-story US-SADM-005
    @aidlc-adr ADR-005, ADR-006, ADR-007

    Variables: $rows (ShopCurrency paginator); $form, $editingId, $sortField, $sortDir.
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: add / edit form --}}
    <x-gp247::card :title="gp247_language_render($editingId ? 'action.edit' : 'admin.currency.add_new')">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gp247::input :label="gp247_language_render('admin.currency.name')" name="name"
                    wire:model="form.name" :error="$errors->first('form.name')" required />
                <x-gp247::input :label="gp247_language_render('admin.currency.code')" name="code"
                    wire:model="form.code" :error="$errors->first('form.code')" required />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-gp247::input :label="gp247_language_render('admin.currency.symbol')" name="symbol"
                    wire:model="form.symbol" :error="$errors->first('form.symbol')" required />
                {{-- WHY: step="any" (not a fixed 0.0001) so a small snapshot rate such as
                     0.00004 (a large currency vs a small-unit base) can be typed — a fixed step
                     rejects values that are not its multiple. Matches decimal(16,6) storage;
                     server validation (numeric, gt:0) enforces correctness.
                     ADR compat-foundation_exchange-rate-precision / US-CMP-exchange-rate-precision. --}}
                <x-gp247::input type="number" step="any" :label="gp247_language_render('admin.currency.exchange_rate')"
                    name="exchange_rate" wire:model="form.exchange_rate" :error="$errors->first('form.exchange_rate')" required />
                <x-gp247::input type="number" min="0" max="8" :label="gp247_language_render('admin.currency.precision')"
                    name="precision" wire:model="form.precision" :error="$errors->first('form.precision')" required />
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="space-y-1">
                    <label for="symbol_first" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('admin.currency.symbol_first') }}</label>
                    <select id="symbol_first" wire:model="form.symbol_first"
                        class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                        <option value="0">{{ gp247_language_render('admin.no') }}</option>
                        <option value="1">{{ gp247_language_render('admin.yes') }}</option>
                    </select>
                    @error('form.symbol_first') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-gp247::input :label="gp247_language_render('admin.currency.thousands')" name="thousands"
                    wire:model="form.thousands" :error="$errors->first('form.thousands')" required />
                <x-gp247::input type="number" min="0" :label="gp247_language_render('admin.sort')"
                    name="sort" wire:model="form.sort" :error="$errors->first('form.sort')" required />
            </div>
            <x-gp247::checkbox :label="gp247_language_render('admin.active')" wire:model="form.status" value="1" />
            <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                <x-gp247::button variant="secondary" wire:click="cancelEdit" data-testid="admin-currency-form-cancel">{{ gp247_language_render($editingId ? 'admin.cancel' : 'admin.reset') }}</x-gp247::button>
                <x-gp247::button type="submit" wire:loading.attr="disabled">
                    <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.update' : 'admin.submit') }}
                </x-gp247::button>
            </div>
        </form>
    </x-gp247::card>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.currency.title')">
        <div class="mb-3 flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="keyword" placeholder="{{ gp247_language_render('admin.currency.name') }}"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
            {{-- Change base: separate, guarded action (opens the warning modal). --}}
            <x-gp247::button size="sm" variant="secondary" type="button" class="shrink-0 whitespace-nowrap"
                x-on:click="$dispatch('open-modal', 'currency-rebase')"
                data-testid="shop-admin-currency-rebase-button">
                <i class="fas fa-exchange-alt"></i> {{ gp247_language_render('admin.currency.rebase_button') }}
            </x-gp247::button>
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('name')">
                        {{ gp247_language_render('admin.currency.name') }} @if ($sortField === 'name')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('code')">
                        {{ gp247_language_render('admin.currency.code') }} @if ($sortField === 'code')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('status')">
                        {{ gp247_language_render('admin.status') }} @if ($sortField === 'status')<span class="text-[10px]">{{ $sortDir === 'asc' ? '▲' : '▼' }}</span>@endif
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-100 border-l-4 border-blue-500 dark:bg-blue-900 dark:border-blue-500' : '' }}" wire:key="currency-{{ $row->id }}">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $row->name }} <span class="text-xs text-gray-400">{{ $row->symbol }}</span></td>
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                        {{ $row->code }}
                        @if ($row->is_base)
                            <x-gp247::badge color="blue" class="ml-1" data-testid="shop-admin-currency-base-badge">{{ gp247_language_render('admin.currency.base_label') }}</x-gp247::badge>
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-gp247::badge :color="$row->status ? 'green' : 'gray'">{{ $row->status ? gp247_language_render('admin.active') : gp247_language_render('admin.inactive') }}</x-gp247::badge></td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            {{-- Base currency is edit/delete-locked (changed only via "Change base"). --}}
                            @if ($row->is_base)
                                <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500" title="{{ gp247_language_render('admin.currency.base_locked') }}">
                                    <i class="fas fa-lock"></i>
                                </span>
                            @else
                                <x-gp247::button size="sm" variant="ghost" wire:click="editRow('{{ $row->id }}')" data-testid="admin-currency-list-edit"><i class="fas fa-edit"></i></x-gp247::button>
                                <x-gp247::button size="sm" variant="ghost" wire:click="delete('{{ $row->id }}')" wire:confirm="{{ gp247_language_render('action.delete_confirm') }}"><i class="fas fa-trash-alt text-red-600"></i></x-gp247::button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>

    {{-- Change-base (rebase) modal: value-preserving base switch with a full
         warning and an explicit "I understand" gate. ADR currency-rebase-value-preserving. --}}
    <x-gp247::modal name="currency-rebase" :title="gp247_language_render('admin.currency.rebase_title')">
        <div class="space-y-4" data-testid="shop-admin-currency-rebase-modal">

            {{-- Full impact warning. --}}
            <x-gp247::alert type="warning">
                {{ gp247_language_render('admin.currency.rebase_warning') }}
            </x-gp247::alert>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('admin.currency.rebase_current_base') }}</label>
                <p class="mt-1 text-sm text-gray-800 dark:text-gray-100">
                    @if ($baseCurrency)
                        {{ $baseCurrency->name }} <span class="text-gray-400">({{ $baseCurrency->code }})</span>
                    @else
                        <span class="text-red-600">{{ gp247_language_render('admin.currency.base_none') }}</span>
                    @endif
                </p>
            </div>

            {{-- Target currency to become the new base (active, non-base only). --}}
            <div class="space-y-1">
                <label for="rebaseTarget" class="block text-sm font-medium text-gray-700 dark:text-gray-200">{{ gp247_language_render('admin.currency.rebase_target') }}</label>
                <select id="rebaseTarget" wire:model.live="rebaseTarget" data-testid="shop-admin-currency-rebase-target"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                    <option value="">{{ gp247_language_render('admin.currency.rebase_target_placeholder') }}</option>
                    @foreach ($rebaseCandidates as $candidate)
                        <option value="{{ $candidate->code }}">{{ $candidate->name }} ({{ $candidate->code }})</option>
                    @endforeach
                </select>
                @error('rebaseTarget') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- New rate for the OUTGOING base (suggested = 1 / target's current rate). --}}
            <div class="space-y-1">
                <label for="rebaseOldRate" class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    {{ gp247_language_render('admin.currency.rebase_old_rate') }}
                    @if ($baseCurrency)<span class="text-gray-400">({{ $baseCurrency->code }})</span>@endif
                </label>
                <input id="rebaseOldRate" type="number" step="any" wire:model="rebaseOldRate" data-testid="shop-admin-currency-rebase-old-rate-input"
                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.currency.rebase_old_rate_help') }}</p>
                @error('rebaseOldRate') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Explicit understanding gate. --}}
            <label class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model.live="rebaseConfirmed" data-testid="shop-admin-currency-rebase-confirm-check"
                    class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                <span>{{ gp247_language_render('admin.currency.rebase_confirm') }}</span>
            </label>
            @error('rebaseConfirmed') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <x-slot:footer>
            <x-gp247::button variant="secondary" type="button" x-on:click="$dispatch('close-modal', 'currency-rebase')">{{ gp247_language_render('admin.cancel') }}</x-gp247::button>
            {{-- Submit stays disabled until the understanding checkbox is ticked. --}}
            <x-gp247::button type="button" wire:click="rebase" wire:loading.attr="disabled"
                x-bind:disabled="! $wire.rebaseConfirmed"
                data-testid="shop-admin-currency-rebase-submit">
                <i class="fas fa-exchange-alt"></i> {{ gp247_language_render('admin.currency.rebase_submit') }}
            </x-gp247::button>
        </x-slot:footer>
    </x-gp247::modal>
</div>
