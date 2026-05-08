@php
    // Avoid match() inside @php — Blade brace tokeniser can misread it
    if ($var->approval_status === 'approved') {
        $statusClasses = 'border-success/30 bg-success/5';
    } elseif ($var->approval_status === 'rejected') {
        $statusClasses = 'border-danger/30 bg-danger/5';
    } else {
        $statusClasses = 'border-line bg-white';
    }
    $isRepeating    = ($var->occurrences ?: 1) > 1;
    $thisCardHasErr = $errors->any() && session('error_variable_id') === $var->id;
    $editingInit    = $thisCardHasErr ? 'true' : 'false';
    $currentMode    = $var->value_mode ?: 'ask_each_time';
    $pages          = collect($var->text_positions ?? [])->pluck('page')->unique()->filter()->sort()->values();

    // Routes for fetch() calls — evaluated server-side once, safe in Alpine
    $approveUrl = route('templates.variables.approve', [$template->id, $var->id]);
    $rejectUrl  = route('templates.variables.reject',  [$template->id, $var->id]);
    $undoUrl    = route('templates.variables.undo',    [$template->id, $var->id]);
    $updateUrl  = route('templates.variables.update',  [$template->id, $var->id]);
    $modeUrl    = route('templates.variables.update-mode', [$template->id, $var->id]);
@endphp
{{--
  INLINE APPROVE/REJECT — no page reload, no scroll jump.

  Alpine x-data state machine:
    status  : 'pending' | 'approved' | 'rejected'    — mirrors approval_status
    loading : null | 'approving' | 'rejecting' | 'undoing'
    error   : null | string

  All approve/reject/undo actions call the JSON endpoint (Accept: application/json).
  On success, the card updates in-place and dispatches 'rd-status-change' so the
  tab badges update without page reload.

  x-data uses @json() for label/type so quotes don't break the HTML attribute.
--}}
<div class="rounded-2xl border-2 transition-all duration-200"
     :class="{
         'border-success/30 bg-success/5': status === 'approved',
         'border-danger/30 bg-danger/5':   status === 'rejected',
         'border-line bg-white':           status === 'pending',
     }"
     x-data="{
         status:    @json($var->approval_status),
         loading:   null,
         error:     null,
         editing:   {{ $editingInit }},
         label:     @json(old('label', $var->label)),
         type:      @json(old('type', $var->type)),
         valueMode: @json($currentMode),

         csrf() {
             return document.querySelector('meta[name=csrf-token]')?.content ?? '';
         },

         async doAction(action, url) {
             if (this.loading) return;
             this.loading = action;
             this.error   = null;
             try {
                 const res  = await fetch(url, {
                     method: 'POST',
                     headers: {
                         'Accept':       'application/json',
                         'Content-Type': 'application/json',
                         'X-CSRF-TOKEN': this.csrf(),
                     },
                 });
                 const data = await res.json();
                 if (!res.ok || !data.success) throw new Error(data.message ?? 'Request failed');
                 this.status = data.status;
                 // Notify parent so tab badges update without page reload
                 this.$dispatch('rd-status-change', {
                     from:     action === 'undo' ? (this.status === 'pending' ? data.status : 'pending') : this.status,
                     to:       data.status,
                     counts:   data.counts,
                     readiness: data.readiness,
                     label:    data.label,
                 });
             } catch (e) {
                 this.error = e.message || 'Something went wrong. Please try again.';
             } finally {
                 this.loading = null;
             }
         },

         approve() { this.doAction('approving', @json($approveUrl)); },
         reject()  { this.doAction('rejecting', @json($rejectUrl));  },
         undo()    { this.doAction('undoing',   @json($undoUrl));    },
     }"
     style="padding: 1.25rem;">

    {{-- ── Inline error bar ─────────────────────────────────────────── --}}
    <div x-show="error" x-cloak
         class="mb-3 p-3 bg-danger/10 border border-danger/20 rounded-xl text-xs text-danger flex items-center gap-2"
         role="alert" aria-live="polite">
        <x-icon name="alert-circle" class="w-4 h-4 flex-shrink-0" />
        <span x-text="error"></span>
        <button type="button" @click="error = null"
                class="ml-auto text-muted hover:text-danger transition-colors">
            <x-icon name="x" class="w-3.5 h-3.5" />
        </button>
    </div>

    {{-- ── View mode ─────────────────────────────────────────────────── --}}
    <div x-show="!editing">

        {{-- Header row: badges + edit button --}}
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="px-2 py-0.5 rounded-lg text-xs font-medium {{ $var->typeBadgeColor() }}">
                    {{ $var->type }}
                </span>

                @if($isRepeating)
                <span class="px-2 py-0.5 rounded-lg text-xs font-medium bg-primary/10 text-primary flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Repeating ×{{ $var->occurrences }}
                </span>
                @endif

                {{-- Approval status badge — updates reactively via Alpine --}}
                <span x-show="status === 'approved'"
                      class="text-xs text-success font-medium flex items-center gap-1" aria-live="polite">
                    <x-icon name="check-circle" class="w-3.5 h-3.5" /> Approved
                </span>
                <span x-show="status === 'rejected'"
                      class="text-xs text-danger font-medium flex items-center gap-1" aria-live="polite">
                    <x-icon name="x" class="w-3.5 h-3.5" /> Rejected
                </span>

                {{-- Value mode badge --}}
                @if($var->isFixed())
                <span class="px-2 py-0.5 bg-success/10 text-success text-xs rounded-full font-medium flex items-center gap-1">
                    <x-icon name="lock" class="w-3 h-3" /> Fixed
                </span>
                @elseif($var->isDefault())
                <span class="px-2 py-0.5 bg-blue-soft text-slate text-xs rounded-full font-medium">
                    Default
                </span>
                @endif

                @if($var->needs_review ?? false)
                <span class="px-2 py-0.5 bg-warning/10 text-warning text-xs rounded-full font-medium flex items-center gap-1">
                    <x-icon name="alert-circle" class="w-3 h-3" /> Needs review
                </span>
                @endif
            </div>

            <button @click="editing = true"
                    class="p-2 text-muted hover:text-primary hover:bg-blue-soft rounded-xl transition-colors flex-shrink-0 ml-2"
                    title="Edit field" aria-label="Edit {{ $var->label }}">
                <x-icon name="pencil" class="w-4 h-4" />
            </button>
        </div>

        {{-- Field info --}}
        <div class="mb-4">
            <h4 class="font-semibold text-navy text-base leading-tight">{{ $var->label }}</h4>
            <p class="text-xs text-muted font-mono mt-0.5">&#123;&#123; {{ $var->name }} &#125;&#125;</p>

            @if($var->isFixed() && $var->fixed_value)
            <p class="text-sm text-slate mt-1.5">
                Fixed as: <span class="font-medium text-navy">{{ Str::limit($var->fixed_value, 80) }}</span>
            </p>
            @elseif($var->example_value)
            <p class="text-sm text-slate mt-1.5">
                e.g. <span class="font-medium text-navy">{{ Str::limit($var->example_value, 80) }}</span>
            </p>
            @endif

            @if($var->description)
            <p class="text-xs text-muted mt-1">{{ $var->description }}</p>
            @endif

            @if($var->needs_review_reason ?? false)
            <p class="text-xs text-warning mt-1 flex items-center gap-1">
                <x-icon name="alert-circle" class="w-3 h-3" />
                {{ Str::limit($var->needs_review_reason, 100) }}
            </p>
            @endif

            @if($pages->isNotEmpty())
            <p class="text-xs text-muted mt-1">
                Page{{ $pages->count() > 1 ? 's' : '' }}: {{ $pages->implode(', ') }}
            </p>
            @endif
        </div>

        {{-- Action buttons — inline, no form POST, no page reload --}}
        <div class="flex gap-2" aria-label="Variable actions">

            {{-- Loading overlay shown during any action --}}
            <template x-if="loading">
                <div class="flex items-center gap-2 text-xs text-muted py-2.5 px-3 bg-blue-soft rounded-xl flex-1">
                    <x-spinner size="sm" />
                    <span x-text="loading === 'approving'
                        ? 'Adding this field…'
                        : (loading === 'rejecting'
                            ? 'Ignoring this suggestion…'
                            : 'Updating…')"></span>
                </div>
            </template>

            {{-- ── PENDING state actions ──────────────────────────────────── --}}
            <template x-if="!loading && status === 'pending'">
                <div class="flex gap-2 flex-1">
                    <button @click="approve()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-success text-white py-2.5 rounded-xl text-sm font-medium hover:bg-green-600 transition-colors"
                            aria-label="Approve {{ $var->label }}">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        Approve
                    </button>
                    <button @click="reject()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-danger/10 text-danger py-2.5 rounded-xl text-sm font-medium hover:bg-danger/20 transition-colors"
                            aria-label="Reject {{ $var->label }}">
                        <x-icon name="x" class="w-4 h-4" />
                        Reject
                    </button>
                </div>
            </template>

            {{-- ── APPROVED state actions ─────────────────────────────────── --}}
            <template x-if="!loading && status === 'approved'">
                <div class="flex gap-2 flex-1">
                    {{-- Undo --}}
                    <button @click="undo()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-blue-soft text-slate py-2.5 rounded-xl text-sm font-medium hover:bg-blue-light transition-colors"
                            aria-label="Undo approval of {{ $var->label }}">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        Undo
                    </button>
                    <button @click="reject()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-danger/10 text-danger py-2.5 rounded-xl text-sm font-medium hover:bg-danger/20 transition-colors"
                            aria-label="Reject {{ $var->label }}">
                        <x-icon name="x" class="w-4 h-4" />
                        Reject
                    </button>
                </div>
            </template>

            {{-- ── REJECTED state actions ─────────────────────────────────── --}}
            <template x-if="!loading && status === 'rejected'">
                <div class="flex gap-2 flex-1">
                    <button @click="approve()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-success text-white py-2.5 rounded-xl text-sm font-medium hover:bg-green-600 transition-colors"
                            aria-label="Approve {{ $var->label }}">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        Approve
                    </button>
                    <button @click="undo()"
                            class="flex-1 flex items-center justify-center gap-1.5 bg-blue-soft text-slate py-2.5 rounded-xl text-sm font-medium hover:bg-blue-light transition-colors"
                            aria-label="Undo rejection of {{ $var->label }}">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        Undo
                    </button>
                </div>
            </template>

        </div>
    </div>

    {{-- ── Edit mode ─────────────────────────────────────────────────── --}}
    <div x-show="editing" x-cloak>
        {{-- Validation errors scoped to this variable only --}}
        @if($thisCardHasErr)
        <div class="mb-3 p-3 bg-danger/10 border border-danger/20 rounded-xl text-xs text-danger">
            @foreach($errors->all() as $error)
            <p>{{ $error }}</p>
            @endforeach
        </div>
        @endif

        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-navy">Edit Field</h4>
            <button type="button" @click="editing = false"
                    class="p-1.5 text-muted hover:text-navy rounded-lg transition-colors"
                    aria-label="Close edit mode">
                <x-icon name="x" class="w-4 h-4" />
            </button>
        </div>

        {{-- Field metadata form — standard POST (editing is a separate action, not AJAX) --}}
        <form method="POST" action="{{ $updateUrl }}">
            @csrf
            @method('PATCH')
            <div class="space-y-3 mb-4">
                <div>
                    <label class="block text-xs font-medium text-navy mb-1">Label</label>
                    <input type="text" name="label" x-model="label"
                           class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-medium text-navy mb-1">Type</label>
                    <select name="type" x-model="type"
                            class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors bg-white">
                        @foreach(['text','date','number','currency','email','phone','address','select'] as $t)
                        <option value="{{ $t }}" {{ $var->type === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_required" id="req_{{ $var->id }}"
                           {{ $var->is_required ? 'checked' : '' }}
                           class="w-4 h-4 rounded border-line text-primary focus:ring-primary/20">
                    <label for="req_{{ $var->id }}" class="text-xs text-slate">Required field</label>
                </div>
            </div>
            <div class="flex gap-2 mb-5">
                <button type="submit"
                        class="flex-1 flex items-center justify-center gap-1.5 bg-primary text-white py-2 rounded-xl text-sm font-medium hover:bg-primary-dark transition-colors"
                        data-loading-text="Saving…">
                    <x-icon name="check-circle" class="w-4 h-4" /> Save changes
                </button>
                <button type="button" @click="editing = false"
                        class="px-4 py-2 bg-blue-soft text-slate rounded-xl text-sm hover:bg-blue-light transition-colors">
                    Cancel
                </button>
            </div>
        </form>

        {{-- Value mode section (approved variables only) --}}
        @if($var->approval_status === 'approved')
        <div class="pt-4 border-t border-line">
            <p class="text-xs font-semibold text-navy mb-2">How should Loopi handle this field?</p>
            <div class="flex flex-wrap gap-2 mb-3">
                <button type="button" @click="valueMode = 'ask_each_time'"
                        :class="valueMode === 'ask_each_time'
                            ? 'bg-slate text-white border-slate'
                            : 'bg-white text-slate border-line hover:border-slate'"
                        class="flex items-center gap-1 px-2.5 py-1.5 border rounded-lg text-xs font-medium transition-all">
                    <x-icon name="refresh" class="w-3 h-3" /> Ask every time
                </button>
                <button type="button" @click="valueMode = 'default_editable'"
                        :class="valueMode === 'default_editable'
                            ? 'bg-primary text-white border-primary'
                            : 'bg-white text-primary border-line hover:border-primary'"
                        class="flex items-center gap-1 px-2.5 py-1.5 border rounded-lg text-xs font-medium transition-all">
                    <x-icon name="pencil" class="w-3 h-3" /> Use as default
                </button>
                <button type="button" @click="valueMode = 'fixed_hidden'"
                        :class="valueMode === 'fixed_hidden'
                            ? 'bg-success text-white border-success'
                            : 'bg-white text-success border-line hover:border-success'"
                        class="flex items-center gap-1 px-2.5 py-1.5 border rounded-lg text-xs font-medium transition-all">
                    <x-icon name="lock" class="w-3 h-3" /> Keep as fixed
                </button>
            </div>

            <p x-show="valueMode === 'ask_each_time'" class="text-xs text-muted mb-2">
                This field appears normally every time. Good for names, dates, amounts.
            </p>
            <p x-show="valueMode === 'default_editable'" class="text-xs text-muted mb-2">
                Pre-filled with your saved value. User can still edit before generating.
            </p>
            <p x-show="valueMode === 'fixed_hidden'" class="text-xs text-muted mb-2">
                Hidden from the form and automatically used in every document.
            </p>

            <form method="POST" action="{{ $modeUrl }}">
                @csrf
                <input type="hidden" name="value_mode" x-bind:value="valueMode">

                <div x-show="valueMode === 'fixed_hidden' || valueMode === 'default_editable'" x-cloak class="mb-3">
                    <label class="block text-xs font-medium text-navy mb-1">
                        <span x-show="valueMode === 'fixed_hidden'">Fixed value</span>
                        <span x-show="valueMode === 'default_editable'">Default value</span>
                    </label>
                    <input type="text" name="fixed_value"
                           value="{{ $var->fixed_value ?? $var->default_value ?? $var->example_value }}"
                           placeholder="Enter value to save"
                           class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                </div>

                <button type="submit"
                        class="w-full flex items-center justify-center gap-1.5 bg-success/10 text-success py-2 rounded-xl text-sm font-medium hover:bg-success/20 transition-colors"
                        data-loading-text="Saving…">
                    <x-icon name="check-circle" class="w-4 h-4" />
                    <span x-show="valueMode === 'fixed_hidden'">Save as fixed</span>
                    <span x-show="valueMode === 'default_editable'">Save as default</span>
                    <span x-show="valueMode === 'ask_each_time'">Reset to Ask every time</span>
                </button>
            </form>
        </div>
        @endif
    </div>

</div>
