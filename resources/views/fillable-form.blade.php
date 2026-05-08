<x-layouts.app title="{{ $template->name }} — Fill Form — RepetitiveDocs">
<div class="p-4 md:p-8">
<div class="max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="mb-6 md:mb-8">
        <div class="flex items-center gap-3 mb-2">
            <a href="{{ route('templates.editor', $template->id) }}" class="text-muted hover:text-navy transition-colors">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-navy">Fill Your Document</h1>
                <p class="text-sm text-slate mt-0.5">{{ $template->name }}</p>
            </div>
        </div>
        <p class="text-slate text-sm ml-8">Loopi created this form from your template variables</p>
    </div>

    @if(session('error'))
    <div class="mb-6 flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
        <x-icon name="alert-circle" class="w-5 h-5 flex-shrink-0 mt-0.5" />
        {{ session('error') }}
    </div>
    @endif

    <form method="POST" action="{{ route('fillable-form.generate', $template->id) }}">
        @csrf

        <div class="grid lg:grid-cols-3 gap-6 md:gap-8">

            {{-- ── Form fields ─────────────────────────────────── --}}
            <div class="lg:col-span-2 bg-white rounded-2xl border border-line p-6 md:p-8">

                @php
                    $typeGroups = [
                        'text'     => $template->approvedVariables->whereIn('type', ['text', 'select']),
                        'contact'  => $template->approvedVariables->whereIn('type', ['email', 'phone', 'address']),
                        'date'     => $template->approvedVariables->where('type', 'date'),
                        'number'   => $template->approvedVariables->whereIn('type', ['number', 'currency']),
                    ];
                    $groupColors = ['text' => 'primary', 'contact' => 'success', 'date' => 'warning', 'number' => 'warning'];
                    $groupLabels = ['text' => 'Text Fields', 'contact' => 'Contact & Location', 'date' => 'Dates', 'number' => 'Numbers & Amounts'];
                    $stepNum = 0;
                @endphp

                @foreach($typeGroups as $groupKey => $groupVars)
                @if($groupVars->isNotEmpty())
                @php $stepNum++; @endphp

                <div class="{{ !$loop->first ? 'mt-8 pt-8 border-t border-line' : '' }}">
                    <h2 class="text-lg font-semibold text-navy mb-5 flex items-center gap-3">
                        <div class="w-8 h-8 bg-{{ $groupColors[$groupKey] }}/10 rounded-lg flex items-center justify-center flex-shrink-0">
                            <span class="text-{{ $groupColors[$groupKey] }} text-sm font-bold">{{ $stepNum }}</span>
                        </div>
                        {{ $groupLabels[$groupKey] }}
                    </h2>

                    <div class="space-y-5">
                        @foreach($groupVars as $var)
                        <div>
                            <label for="field_{{ $var->name }}" class="block text-sm font-medium text-navy mb-1.5">
                                {{ $var->label }}
                                @if($var->is_required)<span class="text-danger ml-0.5">*</span>@endif
                                @if(($var->occurrences ?: 1) > 1)
                                <span class="ml-2 px-1.5 py-0.5 bg-primary/10 text-primary text-xs rounded-full font-normal">
                                    updates {{ $var->occurrences }} places
                                </span>
                                @endif
                            </label>

                            @if($var->type === 'address')
                            <textarea
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                rows="3"
                                placeholder="{{ $var->example_value ?? 'Enter ' . $var->label }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >{{ old('fields.' . $var->name) }}</textarea>

                            @elseif($var->type === 'currency')
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate font-medium">₱</span>
                                <input
                                    type="text"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name) }}"
                                    placeholder="{{ $var->example_value ?? '0.00' }}"
                                    class="w-full pl-8 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>
                            <p class="text-xs text-muted mt-1">Currency formatting will be applied automatically</p>

                            @elseif($var->type === 'date')
                            <input
                                type="date"
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                value="{{ old('fields.' . $var->name) }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >

                            @elseif($var->type === 'email')
                            <div class="relative">
                                <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                                <input
                                    type="email"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name) }}"
                                    placeholder="{{ $var->example_value ?? 'email@example.com' }}"
                                    class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>

                            @elseif($var->type === 'phone')
                            <div class="relative">
                                <x-icon name="phone" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                                <input
                                    type="tel"
                                    id="field_{{ $var->name }}"
                                    name="fields[{{ $var->name }}]"
                                    value="{{ old('fields.' . $var->name) }}"
                                    placeholder="{{ $var->example_value ?? '+63 XXX XXX XXXX' }}"
                                    class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                                >
                            </div>

                            @else
                            <input
                                type="{{ in_array($var->type, ['number']) ? 'number' : 'text' }}"
                                id="field_{{ $var->name }}"
                                name="fields[{{ $var->name }}]"
                                value="{{ old('fields.' . $var->name) }}"
                                placeholder="{{ $var->example_value ?? 'Enter ' . $var->label }}"
                                class="w-full px-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('fields.'.$var->name) border-danger @enderror"
                            >
                            @endif

                            @error('fields.' . $var->name)
                            <p class="text-xs text-danger mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach

                <div class="mt-8 pt-6 border-t border-line">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-primary text-white py-4 rounded-xl font-semibold hover:bg-primary-dark transition-colors text-sm"
                            data-loading-text="Loopi is building your document…">
                        <x-icon name="sparkles" class="w-5 h-5" />
                        Generate Document
                    </button>
                    <p class="text-xs text-muted text-center mt-3">
                        No copy-paste. Loopi does the repetitive part.
                    </p>
                </div>
            </div>

            {{-- ── Right: Info + summary ───────────────────────── --}}
            <div class="space-y-5">

                {{-- Field count card --}}
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white">
                    <h3 class="font-semibold mb-3 flex items-center gap-2">
                        <x-icon name="file-text" class="w-5 h-5" />
                        {{ $template->name }}
                    </h3>
                    @php
                        $repeatingCount = $template->approvedVariables->filter(fn($v) => ($v->occurrences ?: 1) > 1)->count();
                        $totalPlacements = $template->approvedVariables->sum(fn($v) => $v->occurrences ?: 1);
                    @endphp
                    <div class="space-y-2 text-sm text-white/90">
                        <p>{{ $template->approvedVariables->count() }} fields to fill</p>
                        @if($totalPlacements > $template->approvedVariables->count())
                        <p class="text-white/80">Updates {{ $totalPlacements }} places in your document</p>
                        @endif
                        @if($repeatingCount > 0)
                        <p class="text-white/80">{{ $repeatingCount }} field{{ $repeatingCount === 1 ? '' : 's' }} appear multiple times</p>
                        @endif
                        @if($template->document_type)
                        <p>Type: {{ $template->document_type }}</p>
                        @endif
                    </div>
                </div>

                {{-- Loopi tip --}}
                <div class="bg-white rounded-2xl border border-line p-5">
                    <div class="flex gap-3">
                        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                             class="w-14 h-14 object-contain flex-shrink-0">
                        <div class="text-sm text-slate">
                            <p class="font-medium text-navy mb-1">Loopi's Tip</p>
                            @if($repeatingCount > 0)
                            <p>Fields tagged <span class="text-primary font-medium">updates N places</span> appear multiple times. Edit once and Loopi updates all linked placements.</p>
                            @else
                            <p>Fill in all required fields marked with <span class="text-danger font-bold">*</span> to generate your document.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Variable list preview --}}
                <div class="bg-white rounded-2xl border border-line p-5">
                    <h3 class="text-sm font-semibold text-navy mb-3">Fields in this form</h3>
                    <div class="space-y-2">
                        @foreach($template->approvedVariables as $var)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-slate truncate mr-2">{{ $var->label }}</span>
                            <span class="px-2 py-0.5 rounded text-xs {{ $var->typeBadgeColor() }} flex-shrink-0">{{ $var->type }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

            </div>

        </div>
    </form>

</div>
</div>
</x-layouts.app>
