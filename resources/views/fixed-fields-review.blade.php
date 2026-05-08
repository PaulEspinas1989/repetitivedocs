<x-layouts.app title="Save Answers for Next Time — RepetitiveDocs">
<div class="p-4 md:p-8">
<div class="max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 md:mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ isset($generated) ? route('generation-result', $generated->id) : route('templates.editor', $template->id) }}"
               class="text-muted hover:text-navy transition-colors">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-navy">Save answers for next time</h1>
                <p class="text-sm text-slate mt-0.5">{{ $template->name }}</p>
            </div>
        </div>
    </div>

    {{-- Loopi intro --}}
    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white mb-8">
        <div class="flex items-start gap-4">
            <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                 class="w-20 h-20 object-contain flex-shrink-0 hidden sm:block"
                 style="filter:brightness(0) invert(1)">
            <div>
                <h2 class="text-lg font-bold mb-2">Loopi can remember the answers that stay the same</h2>
                <p class="text-white/90 text-sm">
                    Choose how each field should behave for future documents.
                    Fixed fields are filled automatically — you won't have to type them again.
                </p>
                <div class="flex flex-wrap gap-2 mt-3">
                    <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                        <x-icon name="lock" class="w-3 h-3 inline mr-1" />Keep as fixed — hidden, auto-filled
                    </span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                        <x-icon name="pencil" class="w-3 h-3 inline mr-1" />Use as default — pre-filled, editable
                    </span>
                    <span class="px-3 py-1 bg-white/20 rounded-full text-xs font-medium">
                        <x-icon name="refresh" class="w-3 h-3 inline mr-1" />Ask every time
                    </span>
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('fixed-fields.save', $template->id) }}">
        @csrf
        @if(isset($generated))
            <input type="hidden" name="generation_id" value="{{ $generated->id }}">
        @endif

        {{-- Bulk action bar --}}
        <div class="flex flex-wrap items-center gap-2 mb-4 p-4 bg-white rounded-xl border border-line">
            <span class="text-sm font-medium text-navy mr-2 hidden sm:inline">Apply to all:</span>
            <button type="button" onclick="setAllModes('fixed_hidden')"
                    class="px-3 py-1.5 bg-success/10 text-success text-xs rounded-lg font-medium hover:bg-success/20 transition-colors">
                Keep all as fixed
            </button>
            <button type="button" onclick="setAllModes('default_editable')"
                    class="px-3 py-1.5 bg-primary/10 text-primary text-xs rounded-lg font-medium hover:bg-primary/20 transition-colors">
                Use all as default
            </button>
            <button type="button" onclick="setAllModes('ask_each_time')"
                    class="px-3 py-1.5 bg-slate/10 text-slate text-xs rounded-lg font-medium hover:bg-slate/20 transition-colors">
                Ask all every time
            </button>
        </div>

        {{-- Variable list --}}
        <div class="space-y-3 mb-8">
            @foreach($template->approvedVariables as $var)
            @php
                $lastValue   = $lastValues[$var->name] ?? null;
                $suggestion  = $suggestions[$var->name] ?? [];
                $sugMode     = $suggestion['suggested_mode'] ?? 'ask_each_time';
                $sugReason   = $suggestion['reason'] ?? '';
                $isSensitive = $suggestion['sensitive'] ?? false;
                $currentMode = $var->value_mode ?: 'ask_each_time';
                // If value_mode is already confirmed, use it; else use AI suggestion
                $defaultMode = $var->user_confirmed_mode ? $currentMode : $sugMode;
                $displayVal  = $lastValue ?? $var->fixed_value ?? $var->default_value ?? '';
            @endphp
            <div class="bg-white rounded-2xl border border-line p-5"
                 x-data="{ mode: '{{ $defaultMode }}', showWarn: false }">

                {{-- Field header --}}
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-navy text-sm">{{ $var->label }}</h3>
                            <span class="px-2 py-0.5 rounded text-xs {{ $var->typeBadgeColor() }}">
                                {{ $var->type }}
                            </span>
                            @if($var->isRepeating())
                            <span class="px-2 py-0.5 bg-primary/10 text-primary text-xs rounded-full">
                                × {{ $var->occurrences }} places
                            </span>
                            @endif
                            @if($isSensitive)
                            <span class="px-2 py-0.5 bg-warning/10 text-warning text-xs rounded-full flex items-center gap-1">
                                <x-icon name="alert-circle" class="w-3 h-3" />
                                Personal info
                            </span>
                            @endif
                        </div>
                        @if($displayVal)
                        <p class="text-sm text-slate mt-1">
                            Value: <span class="font-medium text-navy">{{ Str::limit($displayVal, 80) }}</span>
                        </p>
                        @endif
                    </div>
                    {{-- AI suggestion badge --}}
                    @if($sugMode === 'fixed_hidden')
                    <span class="px-2 py-1 bg-success/10 text-success text-xs rounded-lg font-medium flex-shrink-0 flex items-center gap-1">
                        <x-icon name="sparkles" class="w-3 h-3" />
                        Loopi suggests: Fixed
                    </span>
                    @elseif($sugMode === 'default_editable')
                    <span class="px-2 py-1 bg-primary/10 text-primary text-xs rounded-lg font-medium flex-shrink-0 flex items-center gap-1">
                        <x-icon name="sparkles" class="w-3 h-3" />
                        Loopi suggests: Default
                    </span>
                    @endif
                </div>

                {{-- AI reason --}}
                @if($sugReason)
                <p class="text-xs text-muted mb-3 italic">{{ $sugReason }}</p>
                @endif

                {{-- Sensitive warning --}}
                @if($isSensitive)
                <div x-show="mode === 'fixed_hidden'" x-cloak
                     class="mb-3 p-3 bg-warning/10 border border-warning/20 rounded-xl text-xs text-warning flex items-start gap-2">
                    <x-icon name="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <p>This looks like personal information. Only keep it fixed if it should appear in <strong>every future document</strong> from this template.</p>
                </div>
                @endif

                {{-- Mode selector --}}
                <input type="hidden" name="modes[{{ $var->name }}]" x-bind:value="mode">

                <div class="flex flex-wrap gap-2 mb-3">
                    {{-- Ask every time --}}
                    <button type="button"
                            @click="mode = 'ask_each_time'"
                            :class="mode === 'ask_each_time'
                                ? 'bg-slate text-white border-slate'
                                : 'bg-white text-slate border-line hover:border-slate'"
                            class="flex items-center gap-1.5 px-3 py-2 border rounded-xl text-xs font-medium transition-all">
                        <x-icon name="refresh" class="w-3.5 h-3.5" />
                        Ask every time
                    </button>

                    {{-- Use as default --}}
                    <button type="button"
                            @click="mode = 'default_editable'"
                            :class="mode === 'default_editable'
                                ? 'bg-primary text-white border-primary'
                                : 'bg-white text-primary border-line hover:border-primary'"
                            class="flex items-center gap-1.5 px-3 py-2 border rounded-xl text-xs font-medium transition-all">
                        <x-icon name="pencil" class="w-3.5 h-3.5" />
                        Use as default
                    </button>

                    {{-- Keep as fixed --}}
                    <button type="button"
                            @click="mode = 'fixed_hidden'"
                            :class="mode === 'fixed_hidden'
                                ? 'bg-success text-white border-success'
                                : 'bg-white text-success border-line hover:border-success'"
                            class="flex items-center gap-1.5 px-3 py-2 border rounded-xl text-xs font-medium transition-all">
                        <x-icon name="lock" class="w-3.5 h-3.5" />
                        Keep as fixed
                    </button>
                </div>

                {{-- Value input for fixed/default modes --}}
                <div x-show="mode === 'fixed_hidden' || mode === 'default_editable'" x-cloak class="mt-2">
                    <label class="block text-xs font-medium text-navy mb-1">
                        <span x-show="mode === 'fixed_hidden'">Fixed value (auto-used in every generation)</span>
                        <span x-show="mode === 'default_editable'">Default value (pre-filled, user can edit)</span>
                    </label>
                    <input type="text"
                           name="fixed_values[{{ $var->name }}]"
                           value="{{ $displayVal }}"
                           placeholder="Enter the value to save"
                           class="w-full px-3 py-2 border border-line rounded-xl text-sm text-navy focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    <p x-show="mode === 'fixed_hidden'" class="text-xs text-muted mt-1">
                        This field will be hidden from the form and automatically filled.
                    </p>
                    <p x-show="mode === 'default_editable'" class="text-xs text-muted mt-1">
                        This value appears pre-filled. The user can still edit it before generating.
                    </p>
                </div>

            </div>
            @endforeach
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <button type="submit"
                    class="flex-1 flex items-center justify-center gap-2 bg-primary text-white py-3.5 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors"
                    data-loading-text="Saving your preferences…">
                <x-icon name="check-circle" class="w-5 h-5" />
                Save template settings
            </button>
            <a href="{{ isset($generated) ? route('generation-result', $generated->id) : route('fillable-form', $template->id) }}"
               class="flex items-center justify-center gap-2 border-2 border-line text-slate py-3.5 px-5 rounded-xl font-medium text-sm hover:border-primary hover:text-navy transition-all">
                Skip for now
            </a>
        </div>

    </form>

</div>
</div>

<script>
function setAllModes(mode) {
    document.querySelectorAll('[data-mode-btn]').forEach(btn => {
        if (btn.dataset.mode === mode) btn.click();
    });
    // Direct approach: set all hidden mode inputs
    document.querySelectorAll('[name^="modes["]').forEach(input => {
        input.value = mode;
    });
    // Trigger Alpine updates by dispatching events
    document.querySelectorAll('[x-data]').forEach(el => {
        if (el._x_dataStack) {
            el._x_dataStack[0].mode = mode;
        }
    });
}
</script>
</x-layouts.app>
