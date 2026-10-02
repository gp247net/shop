{{--
    Payment requests — two-panel screen (form + ledger on the left, list on the right)
    on the ResourcePanel base (ADR-005, ADR-007, ui-tailadmin P1). Only <x-gp247::*>
    components and Tailwind classes already present in the prebuilt admin.css
    (gp247.md §3a). UI text via gp247_language_render.

    @aidlc-unit payment-request
    @aidlc-story US-payment-request-admin-screen
    @aidlc-adr payment-request_generic-money-request

    Variables: $rows (paginator), $open (?PaymentRequest), $movements (Collection),
               $purposeOptions (key => label), $allPurposes, $subject (?array),
               $editingId, $form, $manual, $sortField, $sortDir, $keyword.
--}}
@php
    $inputCls = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100';
    $labelCls = 'mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300';
    $locked = $this->moneyLocked();
@endphp
<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    {{-- Left: form, then the ledger of the open record --}}
    <div class="space-y-6">
        <x-gp247::card>
            <x-slot:header>
                <div class="min-w-0">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100">
                        {!! gp247_language_render($editingId ? 'admin.payment_request.edit_title' : 'admin.payment_request.create_title') !!}@if ($editingId) #{{ $editingId }}@endif
                    </h3>
                    @if ($audit !== null)
                        {{-- Who raised the request and when (and who cancelled it): money records must say who did what. --}}
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400" data-testid="payment-request-audit">
                            {{ gp247_language_render('admin.payment_request.audit_created') }} <span class="font-medium text-gray-700 dark:text-gray-200">{{ $audit['created_by'] }}</span> · {{ $audit['created_at'] }}
                            @if ($audit['updated_at'] !== null) · {{ gp247_language_render('admin.payment_request.audit_updated') }} {{ $audit['updated_at'] }}@endif
                            @if ($audit['cancelled_at'] !== null) · {{ gp247_language_render('admin.payment_request.audit_cancelled') }} <span class="font-medium text-gray-700 dark:text-gray-200">{{ $audit['cancelled_by'] }}</span> · {{ $audit['cancelled_at'] }}@endif
                        </p>
                    @endif
                </div>
                @if ($editingId)
                    <x-gp247::button size="sm" variant="success" wire:click="cancelEdit" data-testid="payment-request-back">
                        <i class="fas fa-plus"></i> {{ gp247_language_render('admin.payment_request.new_request') }}
                    </x-gp247::button>
                @endif
            </x-slot:header>
            <form wire:submit="save" class="space-y-4" data-testid="payment-request-create">

                @if ($this->storeScopeUiVisible())
                    <div>
                        <label class="{{ $labelCls }}">{{ gp247_language_render('admin.store.scope_label') }}</label>
                        @if ($this->showStorePicker())
                            <select wire:model.live="formStoreId" data-testid="payment-request-store-select" class="{{ $inputCls }}">
                                <option value="">— {{ gp247_language_render('admin.store.select_store') }} —</option>
                                @foreach ($this->storeOptions() as $sid => $stitle)
                                    <option value="{{ $sid }}">{{ $stitle }}</option>
                                @endforeach
                            </select>
                            @error('formStoreId') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        @else
                            <div class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                <i class="fas fa-store text-gray-400"></i> {{ $this->currentStoreLabel() }}
                            </div>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="{{ $labelCls }}">{{ gp247_language_render('admin.payment_request.direction') }}</label>
                        <select wire:model.live="form.direction" data-testid="payment-request-form-direction" class="{{ $inputCls }}" @disabled($locked)>
                            <option value="in">{{ gp247_language_render('admin.payment_request.direction_in') }}</option>
                            <option value="out">{{ gp247_language_render('admin.payment_request.direction_out') }}</option>
                        </select>
                        @error('form.direction') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="{{ $labelCls }}">{{ gp247_language_render('admin.payment_request.purpose') }}</label>
                        <select wire:model.live="form.purpose" data-testid="payment-request-form-purpose" class="{{ $inputCls }}">
                            @foreach ($purposeOptions as $pkey => $plabel)
                                <option value="{{ $pkey }}">{{ $plabel }}</option>
                            @endforeach
                            @if (($form['purpose'] ?? '') !== '' && !array_key_exists($form['purpose'], $purposeOptions))
                                <option value="{{ $form['purpose'] }}">{{ $this->purposeLabel($form['purpose']) }}</option>
                            @endif
                        </select>
                        @error('form.purpose') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-gp247::input type="number" step="any" min="0"
                        :label="gp247_language_render('admin.payment_request.amount')" name="amount"
                        wire:model="form.amount" data-testid="payment-request-form-amount"
                        :error="$errors->first('form.amount')" :disabled="$locked" required />
                    @if ($currencyOptions !== null)
                        <div>
                            <label class="{{ $labelCls }}">{{ gp247_language_render('admin.payment_request.currency') }} <span class="text-red-500">*</span></label>
                            <select wire:model="form.currency" data-testid="payment-request-form-currency" class="{{ $inputCls }}" @disabled($locked) required>
                                @foreach ($currencyOptions as $ccode => $clabel)
                                    <option value="{{ $ccode }}">{{ $clabel }}</option>
                                @endforeach
                            </select>
                            @error('form.currency') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <x-gp247::input
                            :label="gp247_language_render('admin.payment_request.currency')" name="currency"
                            wire:model="form.currency" data-testid="payment-request-form-currency" maxlength="3"
                            :error="$errors->first('form.currency')" :disabled="$locked" required />
                    @endif
                </div>
                @if ($locked)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.money_locked') }}</p>
                @endif

                <x-gp247::input :label="gp247_language_render('admin.payment_request.party_name')" name="party_name"
                    wire:model="form.party_name" data-testid="payment-request-form-party-name" :error="$errors->first('form.party_name')" />
                <div class="grid grid-cols-2 gap-4">
                    <x-gp247::input type="email" :label="gp247_language_render('admin.payment_request.party_email')" name="party_email"
                        wire:model="form.party_email" :error="$errors->first('form.party_email')" />
                    <x-gp247::input :label="gp247_language_render('admin.payment_request.party_phone')" name="party_phone"
                        wire:model="form.party_phone" :error="$errors->first('form.party_phone')" />
                </div>
                <x-gp247::input :label="gp247_language_render('admin.payment_request.description')" name="description"
                    wire:model="form.description" :error="$errors->first('form.description')" />
                <div class="grid grid-cols-2 gap-4">
                    @if ($subjectSpec !== null)
                        {{-- The purpose links to a record: its own name for the id, required. --}}
                        <div>
                            <label class="{{ $labelCls }}">{{ $subjectSpec['label'] }} <span class="text-red-500">*</span></label>
                            <div class="flex gap-2">
                                @if (count($subjectSpec['types']) > 1)
                                    <select wire:model="form.subject_type" data-testid="payment-request-form-subject-type" class="{{ $inputCls }}">
                                        @foreach ($subjectSpec['types'] as $stype => $slabel)
                                            <option value="{{ $stype }}">{{ $slabel }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                <input type="text" wire:model="form.subject_id" data-testid="payment-request-form-subject" required
                                    class="{{ $inputCls }} {{ $errors->has('form.subject_id') ? 'border-red-500' : '' }}">
                            </div>
                            @if ($subjectSpec['help'] !== '')
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $subjectSpec['help'] }}</p>
                            @endif
                            @error('form.subject_type') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            @error('form.subject_id') <span class="mt-1 block text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <x-gp247::input :label="gp247_language_render('admin.payment_request.subject')" name="subject_id"
                            wire:model="form.subject_id" data-testid="payment-request-form-subject" :help="gp247_language_render('admin.payment_request.subject_help')" :error="$errors->first('form.subject_id')" />
                    @endif
                    <x-gp247::input type="date" :label="gp247_language_render('admin.payment_request.expires_at')" name="expires_at"
                        wire:model="form.expires_at" :error="$errors->first('form.expires_at')" />
                </div>

                <div class="flex items-center justify-between border-t border-gray-200 pt-4 dark:border-gray-700">
                    <x-gp247::button variant="secondary" wire:click="cancelEdit" data-testid="payment-request-form-reset">
                        {{ gp247_language_render($editingId ? 'admin.cancel' : 'admin.reset') }}
                    </x-gp247::button>
                    <x-gp247::button type="submit" wire:loading.attr="disabled" data-testid="payment-request-save">
                        <i class="fas fa-save"></i> {{ gp247_language_render($editingId ? 'admin.update' : 'admin.submit') }}
                    </x-gp247::button>
                </div>
            </form>
        </x-gp247::card>

        @if ($open)
            <x-gp247::card :title="gp247_language_render('admin.payment_request.ledger_title')">
                <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <x-gp247::badge :color="$this->statusColor($open->display_status)">{{ gp247_language_render('admin.payment_request.status_' . $open->display_status) }}</x-gp247::badge>
                    <span>{{ gp247_language_render('admin.payment_request.settled') }}: <strong>{{ $this->money($open->settled_amount, $open->currency) }}</strong> / {{ $this->money($open->amount, $open->currency) }}</span>
                </div>

                @if ($subject)
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                        <i class="fas fa-link text-gray-400"></i>
                        @if (!empty($subject['url']))
                            <a href="{{ $subject['url'] }}" class="text-blue-600 hover:underline dark:text-blue-400">{{ $subject['label'] ?? '' }}</a>
                        @else
                            {{ $subject['label'] ?? '' }}
                        @endif
                    </p>
                @endif

                <x-gp247::table :empty="$movements->isEmpty() ? gp247_language_render('admin.payment_request.no_movements') : null">
                    <x-slot:head>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.movement_type') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.amount') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.gateway') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.manual_reference') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.manual_paid_at') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </x-slot:head>
                    @foreach ($movements as $mv)
                        <tr wire:key="payment-movement-{{ $mv->id }}" data-testid="payment-request-movement-item">
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ gp247_language_render('admin.payment_request.movement_' . $mv->type) }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-100">{{ $mv->type === 'refund' ? '−' : '' }}{{ $this->money($mv->amount, $mv->currency) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $mv->gateway }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $mv->reference ?: ($mv->gateway_ref ?: '—') }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $mv->paid_at ? $mv->paid_at->format('Y-m-d H:i') : '—' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if ($this->canRefund($mv))
                                    <x-gp247::button size="sm" variant="warning" wire:click="openRefund({{ $mv->id }})" data-testid="payment-request-refund-open">
                                        <i class="fas fa-undo"></i> {{ gp247_language_render('admin.payment_request.refund_via_gateway') }}
                                    </x-gp247::button>
                                @endif
                            </td>
                        </tr>
                        @if (!empty($mv->note))
                            <tr wire:key="payment-movement-note-{{ $mv->id }}">
                                <td colspan="6" class="px-4 pb-4 text-xs text-gray-500 dark:text-gray-400">{{ $mv->note }}</td>
                            </tr>
                        @endif
                    @endforeach
                </x-gp247::table>

                @if ($refundSources !== [] && $this->canSettleOut())
                    <div class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ gp247_language_render('admin.payment_request.source_title') }}</h4>
                        @foreach ($refundSources as $i => $source)
                            <div class="flex items-center justify-between gap-2 text-sm" wire:key="payment-source-{{ $i }}">
                                <span class="text-gray-600 dark:text-gray-300">{{ app(\GP247\Shop\Payment\GatewayRegistry::class)->label($source->gateway) }} · {{ $source->gatewayRef }} · {{ $source->label }}</span>
                                <x-gp247::button size="sm" variant="warning" wire:click="openSourceRefund({{ $i }})" data-testid="payment-request-source-refund">
                                    <i class="fas fa-undo"></i> {{ gp247_language_render('admin.payment_request.refund_via_gateway') }}
                                </x-gp247::button>
                            </div>
                        @endforeach
                        @if (($sourceRefund['index'] ?? '') !== '')
                            <form wire:submit="refundFromSource" class="space-y-3">
                                <x-gp247::input type="number" step="any" min="0"
                                    :label="gp247_language_render('admin.payment_request.refund_amount')" name="source_refund_amount"
                                    wire:model="sourceRefund.amount" data-testid="payment-request-source-amount" :error="$errors->first('sourceRefund.amount')" required />
                                <div class="flex justify-end">
                                    <x-gp247::button type="submit" variant="warning" wire:loading.attr="disabled"
                                        wire:confirm="{{ gp247_language_render('admin.payment_request.refund_confirm') }}" data-testid="payment-request-source-submit">
                                        <i class="fas fa-undo"></i> {{ gp247_language_render('admin.payment_request.refund_submit') }}
                                    </x-gp247::button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif

                @if (($refund['movement_id'] ?? '') !== '')
                    <form wire:submit="refundViaGateway" class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ gp247_language_render('admin.payment_request.refund_via_gateway') }}</h4>
                        <x-gp247::input type="number" step="any" min="0"
                            :label="gp247_language_render('admin.payment_request.refund_amount')" name="refund_amount"
                            wire:model="refund.amount" data-testid="payment-request-refund-amount" :error="$errors->first('refund.amount')" required />
                        <div class="flex justify-end">
                            <x-gp247::button type="submit" variant="warning" wire:loading.attr="disabled"
                                wire:confirm="{{ gp247_language_render('admin.payment_request.refund_confirm') }}" data-testid="payment-request-refund-submit">
                                <i class="fas fa-undo"></i> {{ gp247_language_render('admin.payment_request.refund_submit') }}
                            </x-gp247::button>
                        </div>
                    </form>
                @endif

                @if ($this->canRecordManual())
                    <form wire:submit="recordManual" class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                            {{ gp247_language_render($open->direction === 'out' ? 'admin.payment_request.record_payout' : 'admin.payment_request.record_receipt') }}
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <x-gp247::input type="number" step="any" min="0"
                                :label="gp247_language_render('admin.payment_request.manual_amount')" name="manual_amount"
                                wire:model="manual.amount" data-testid="payment-request-manual-amount" :error="$errors->first('manual.amount')" required />
                            <x-gp247::input type="date"
                                :label="gp247_language_render('admin.payment_request.manual_paid_at')" name="manual_paid_at"
                                wire:model="manual.paid_at" data-testid="payment-request-manual-paid-at" :error="$errors->first('manual.paid_at')" />
                        </div>
                        <x-gp247::input :label="gp247_language_render('admin.payment_request.manual_reference')" name="manual_reference"
                            wire:model="manual.reference" data-testid="payment-request-manual-reference" :error="$errors->first('manual.reference')" />
                        <div class="flex justify-end">
                            <x-gp247::button type="submit" :variant="$open->direction === 'out' ? 'warning' : 'success'" wire:loading.attr="disabled" data-testid="payment-request-record-manual">
                                <i class="fas fa-check"></i> {{ gp247_language_render($open->direction === 'out' ? 'admin.payment_request.manual_confirm_out' : 'admin.payment_request.manual_confirm_in') }}
                            </x-gp247::button>
                        </div>
                        {{-- Says what the button records: money already moved outside the online gateways. --}}
                        <p class="text-right text-xs text-gray-500 dark:text-gray-400" data-testid="payment-request-manual-hint">
                            {{ gp247_language_render($open->direction === 'out' ? 'admin.payment_request.manual_hint_out' : 'admin.payment_request.manual_hint_in') }}
                        </p>
                    </form>
                @endif

                @if ($open->direction === 'in' && $this->publicLinkAvailable())
                    <div class="mt-4 space-y-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                        @if ($payUrl !== null)
                            <x-gp247::input :label="gp247_language_render('admin.payment_request.public_link')" name="payment_request_public_url"
                                :value="$payUrl" readonly onclick="this.select()" data-testid="payment-request-public-url"
                                :help="gp247_language_render('admin.payment_request.public_link_help')" />
                        @endif
                        @if ($this->canIssueLink())
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($payUrl !== null)
                                    <x-gp247::button size="sm" variant="secondary" x-data
                                        x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js($payUrl))" data-testid="payment-request-copy-link">
                                        <i class="fas fa-copy"></i> {{ gp247_language_render('admin.payment_request.copy_link') }}
                                    </x-gp247::button>
                                @endif
                                @if ($payUrl !== null)
                                    <x-gp247::button size="sm" variant="primary" wire:click="issuePublicLink"
                                        wire:confirm="{{ gp247_language_render('admin.payment_request.reissue_confirm') }}" data-testid="payment-request-issue-link">
                                        <i class="fas fa-link"></i> {{ gp247_language_render('admin.payment_request.reissue_link') }}
                                    </x-gp247::button>
                                @else
                                    <x-gp247::button size="sm" variant="primary" wire:click="issuePublicLink" data-testid="payment-request-issue-link">
                                        <i class="fas fa-link"></i> {{ gp247_language_render('admin.payment_request.issue_link') }}
                                    </x-gp247::button>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 pt-4 text-sm dark:border-gray-700">
                    <span class="text-gray-500 dark:text-gray-400">
                        @if (!$this->publicLinkAvailable())
                            <i class="fas fa-share-alt"></i> {{ gp247_language_render('admin.payment_request.public_link_unavailable') }}
                        @endif
                    </span>
                    @if (!$open->hasMovements() && !$open->isCancelled())
                        <x-gp247::button size="sm" variant="danger" wire:click="cancelRequest"
                            wire:confirm="{{ gp247_language_render('admin.payment_request.cancel_confirm') }}" data-testid="payment-request-cancel">
                            <i class="fas fa-ban"></i> {{ gp247_language_render('admin.payment_request.cancel_request') }}
                        </x-gp247::button>
                    @endif
                </div>
            </x-gp247::card>
        @endif
    </div>

    {{-- Right: list --}}
    <x-gp247::card :title="gp247_language_render('admin.payment_request.list_title')">
        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-4">
            <input type="search" wire:model.live.debounce.300ms="keyword"
                placeholder="{{ gp247_language_render('admin.payment_request.search') }}"
                class="{{ $inputCls }}">
            <select wire:model.live="filterDirection" class="{{ $inputCls }}" data-testid="payment-request-filter-direction">
                <option value="">{{ gp247_language_render('admin.payment_request.direction') }}</option>
                <option value="in">{{ gp247_language_render('admin.payment_request.direction_in') }}</option>
                <option value="out">{{ gp247_language_render('admin.payment_request.direction_out') }}</option>
            </select>
            <select wire:model.live="filterStatus" class="{{ $inputCls }}" data-testid="payment-request-filter-status">
                <option value="">{{ gp247_language_render('admin.status') }}</option>
                @foreach (['open', 'partially_settled', 'settled', 'expired', 'cancelled'] as $st)
                    <option value="{{ $st }}">{{ gp247_language_render('admin.payment_request.status_' . $st) }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterPurpose" class="{{ $inputCls }}" data-testid="payment-request-filter-purpose">
                <option value="">{{ gp247_language_render('admin.payment_request.purpose') }}</option>
                @foreach (array_keys($allPurposes) as $pkey)
                    <option value="{{ $pkey }}">{{ $this->purposeLabel($pkey) }}</option>
                @endforeach
            </select>
        </div>

        <x-gp247::table :empty="$rows->isEmpty() ? gp247_language_render('admin.no_records') : null">
            <x-slot:head>
                <tr>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('id')">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.payment_request.party_name') }}</th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('amount')">{{ gp247_language_render('admin.payment_request.amount') }}</th>
                    <th class="cursor-pointer px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" wire:click="setSort('status')">{{ gp247_language_render('admin.status') }}</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ gp247_language_render('admin.action') }}</th>
                </tr>
            </x-slot:head>

            @foreach ($rows as $row)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 {{ (string) $row->id === (string) $editingId ? 'bg-blue-100 border-l-4 border-blue-500 dark:bg-blue-900 dark:border-blue-500' : '' }}"
                    wire:key="payment-request-{{ $row->id }}" data-testid="payment-request-list-item">
                    <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                        {{ $row->id }}
                        <span class="block text-xs text-gray-400 dark:text-gray-500" data-testid="payment-request-list-created">{{ $row->created_at?->format('Y-m-d H:i') }}</span>
                        @if (!empty($row->created_by))
                            <span class="block text-xs text-gray-500 dark:text-gray-400" data-testid="payment-request-list-creator">{{ $creatorNames[(string) $row->created_by] ?? $row->created_by }}</span>
                        @endif
                        <span class="mt-0.5 block">
                            <x-gp247::badge :color="$row->direction === 'out' ? 'amber' : 'blue'">{{ gp247_language_render('admin.payment_request.direction_' . $row->direction) }}</x-gp247::badge>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-100">
                        {{ $row->party_name ?: '—' }}
                        <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">{{ $this->purposeLabel($row->purpose) }}</span>
                        @if ($this->storeScopeUiVisible())
                            <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500"><i class="fas fa-store"></i> {{ $this->storeLabel($row->store_id) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-100">
                        {{ $this->money($row->amount, $row->currency) }}
                        @if ((float) $row->settled_amount > 0)
                            <span class="mt-0.5 block text-xs text-gray-400 dark:text-gray-500">{{ $this->money($row->settled_amount, $row->currency) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <x-gp247::badge :color="$this->statusColor($row->display_status)">{{ gp247_language_render('admin.payment_request.status_' . $row->display_status) }}</x-gp247::badge>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <x-gp247::button size="sm" variant="ghost" wire:click="editRow('{{ $row->id }}')" data-testid="payment-request-list-open"><i class="fas fa-edit"></i></x-gp247::button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-gp247::table>

        <div class="mt-4">{{ $rows->links('gp247-admin::partials.pagination') }}</div>
    </x-gp247::card>
</div>
