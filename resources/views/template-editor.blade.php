<x-layouts.app title="{{ $template->name }} — Template Editor — RepetitiveDocs">
<div class="flex flex-col h-full"
     x-data="{
         activeTab: '{{ $needs_review->isNotEmpty() ? 'needs_review' : 'pending' }}',
         counts: {
             pending:      {{ $pending->count() }},
             needs_review: {{ $needs_review->count() }},
             approved:     {{ $approved->count() }},
             rejected:     {{ $rejected->count() }},
         },
         readiness: {{ $readiness }},
     }"
     @rd-status-change.window="
         const e = $event.detail;
         if (e.counts) {
             counts.pending      = e.counts.pending;
             counts.needs_review = e.counts.needs_review;
             counts.approved     = e.counts.approved;
             counts.rejected     = e.counts.rejected;
         }
         if (e.readiness !== undefined) readiness = e.readiness;
         // Surface a brief non-intrusive toast via the global system
         if (typeof window.rdToast === 'function') {
             window.rdToast(e.label
                 ? '&quot;' + e.label + '&quot; ' + (e.to === 'approved' ? 'approved.' : e.to === 'rejected' ? 'rejected.' : 'updated.')
                 : 'Field updated.', 'success');
         }
     "
>

    {{-- ── Top bar ────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-line px-6 py-4 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('automation-map', $template->id) }}" class="text-muted hover:text-navy transition-colors flex-shrink-0" title="Back to Automation Map">
                <x-icon name="arrow-left" class="w-5 h-5" />
            </a>
            <div class="min-w-0">
                <h1 class="text-lg font-semibold text-navy truncate">{{ $template->name }}</h1>
                @if($template->document_type)
                <p class="text-xs text-muted">{{ $template->document_type }}</p>
                @endif
            </div>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            {{-- Readiness badge --}}
            <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 bg-blue-soft rounded-xl">
                <x-icon name="trending-up" class="w-4 h-4 text-primary" />
                <span class="text-sm font-semibold text-navy">{{ $readiness }}% ready</span>
            </div>
            @if($approved->count() > 0)
            <a href="{{ route('fillable-form', $template->id) }}"
               class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
                <x-icon name="sparkles" class="w-4 h-4" />
                Generate Form
                <span class="bg-white/20 text-white text-xs px-1.5 py-0.5 rounded-md">{{ $approved->count() }}</span>
            </a>
            @endif
        </div>
    </div>

    {{-- ── Toast ──────────────────────────────────────────────── --}}
    @if(session('toast'))
    <div class="mx-6 mt-4 flex items-center gap-3 p-3 bg-success/10 border border-success/20 rounded-xl text-sm text-success">
        <x-icon name="check-circle" class="w-4 h-4 flex-shrink-0" />
        {{ session('toast') }}
    </div>
    @endif

    <div class="flex flex-1 overflow-hidden gap-0">

        {{-- ── Left: Variable list ────────────────────────────── --}}
        <div class="flex-1 flex flex-col overflow-hidden">

            {{-- Tab bar — counts update reactively via rd-status-change events --}}
            <div class="bg-white border-b border-line px-6 pt-4">
                <div class="flex gap-1 bg-blue-soft p-1 rounded-xl w-fit overflow-x-auto">

                    {{-- Pending tab --}}
                    <button @click="activeTab = 'pending'"
                            :class="activeTab === 'pending' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 whitespace-nowrap">
                        Pending
                        <span x-show="counts.pending > 0"
                              class="px-1.5 py-0.5 bg-primary text-white rounded-full text-xs leading-none"
                              x-text="counts.pending"></span>
                    </button>

                    {{-- Needs Review tab — shown only when AI flagged uncertain variables --}}
                    @if($needs_review->isNotEmpty())
                    <button @click="activeTab = 'needs_review'"
                            :class="activeTab === 'needs_review' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 whitespace-nowrap">
                        <x-icon name="alert-circle" class="w-3.5 h-3.5 text-warning" />
                        Needs Review
                        <span x-show="counts.needs_review > 0"
                              class="px-1.5 py-0.5 bg-warning text-white rounded-full text-xs leading-none"
                              x-text="counts.needs_review"></span>
                    </button>
                    @endif

                    {{-- Approved tab --}}
                    <button @click="activeTab = 'approved'"
                            :class="activeTab === 'approved' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 whitespace-nowrap">
                        Approved
                        <span x-show="counts.approved > 0"
                              class="px-1.5 py-0.5 bg-success text-white rounded-full text-xs leading-none"
                              x-text="counts.approved"></span>
                    </button>

                    {{-- Rejected tab --}}
                    <button @click="activeTab = 'rejected'"
                            :class="activeTab === 'rejected' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2 whitespace-nowrap">
                        Rejected
                        <span x-show="counts.rejected > 0"
                              class="px-1.5 py-0.5 bg-danger text-white rounded-full text-xs leading-none"
                              x-text="counts.rejected"></span>
                    </button>

                    {{-- All tab --}}
                    <button @click="activeTab = 'all'"
                            :class="activeTab === 'all' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap">
                        All
                    </button>
                </div>

                {{-- Approve all pending --}}
                @if($pending->count() > 0)
                <div class="pb-3 mt-3 flex items-center justify-between" x-show="activeTab === 'pending'">
                    <p class="text-xs text-muted" x-text="counts.pending + ' fields waiting for review'"></p>
                    <form method="POST" action="{{ route('templates.editor.approve-all', $template->id) }}">
                        @csrf
                        <button type="submit" class="text-xs text-primary hover:underline font-medium"
                                data-loading-text="Approving all…">
                            Accept all pending →
                        </button>
                    </form>
                </div>
                @endif
            </div>

            {{-- Variable cards --}}
            <div class="flex-1 overflow-y-auto p-6">

                {{-- ── Needs Review tab ─────────────────────────────────── --}}
                <div x-show="activeTab === 'needs_review'">
                    @if($needs_review->isEmpty())
                    <div class="flex flex-col items-center py-16 text-center">
                        <x-icon name="check-circle" class="w-12 h-12 text-success mb-4" />
                        <p class="font-semibold text-navy">Nothing needs review</p>
                        <p class="text-sm text-slate mt-1">Loopi is confident about all detected fields.</p>
                    </div>
                    @else
                    <div class="mb-4 p-4 bg-warning/10 border border-warning/20 rounded-xl">
                        <p class="text-sm font-medium text-navy flex items-center gap-2">
                            <x-icon name="alert-circle" class="w-4 h-4 text-warning" />
                            Loopi found these fields but isn't fully certain they're editable.
                        </p>
                        <p class="text-xs text-slate mt-1">
                            Review them and approve the ones you want to include, or reject the ones Loopi got wrong.
                            Your decisions help make future documents more accurate.
                        </p>
                    </div>
                    <div class="space-y-3">
                        @foreach($needs_review as $var)
                        @include('partials.variable-card', ['var' => $var, 'template' => $template])
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Pending tab --}}
                <div x-show="activeTab === 'pending'">
                    @php
                        $pendingRepeating  = $pending->filter(fn($v) => ($v->occurrences ?: 1) > 1);
                        $pendingStandalone = $pending->filter(fn($v) => ($v->occurrences ?: 1) <= 1);
                    @endphp
                    @if($pending->isEmpty())
                    <div class="flex flex-col items-center py-16 text-center">
                        @if($approved->count() > 0)
                            <x-icon name="check-circle" class="w-12 h-12 text-success mb-4" />
                            <p class="font-semibold text-navy">All fields reviewed!</p>
                            <p class="text-sm text-slate mt-1">{{ $approved->count() }} field{{ $approved->count() === 1 ? '' : 's' }} approved and ready.</p>
                            <a href="{{ route('fillable-form', $template->id) }}"
                               class="mt-5 flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
                                <x-icon name="sparkles" class="w-4 h-4" />
                                Generate Fillable Form
                            </a>
                        @else
                            <x-icon name="alert-circle" class="w-12 h-12 text-warning mb-4" />
                            <p class="font-semibold text-navy">All fields rejected</p>
                            <p class="text-sm text-slate mt-1 max-w-xs">Switch to the Rejected tab to approve some fields, or go back to the automation map to re-analyse.</p>
                            <div class="flex gap-3 mt-5">
                                <button @click="activeTab = 'rejected'"
                                        class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
                                    View Rejected Fields
                                </button>
                                <a href="{{ route('automation-map', $template->id) }}"
                                   class="flex items-center gap-2 border border-line text-slate px-4 py-2 rounded-xl text-sm font-medium hover:border-primary hover:text-navy transition-colors">
                                    Back to Map
                                </a>
                            </div>
                        @endif
                    </div>
                    @else
                        @if($pendingRepeating->isNotEmpty())
                        <div class="mb-2">
                            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                                Repeating ({{ $pendingRepeating->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($pendingRepeating as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if($pendingStandalone->isNotEmpty())
                        <div class="{{ $pendingRepeating->isNotEmpty() ? 'mt-6' : '' }}">
                            <p class="text-xs font-semibold text-slate uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate inline-block"></span>
                                Standalone ({{ $pendingStandalone->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($pendingStandalone as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                </div>

                {{-- Approved tab --}}
                <div x-show="activeTab === 'approved'">
                    @php
                        $approvedRepeating  = $approved->filter(fn($v) => ($v->occurrences ?: 1) > 1);
                        $approvedStandalone = $approved->filter(fn($v) => ($v->occurrences ?: 1) <= 1);
                    @endphp
                    @if($approved->isEmpty())
                    <p class="text-sm text-muted text-center py-12">No approved fields yet.</p>
                    @else
                        @if($approvedRepeating->isNotEmpty())
                        <div class="mb-2">
                            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                                Repeating ({{ $approvedRepeating->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($approvedRepeating as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if($approvedStandalone->isNotEmpty())
                        <div class="{{ $approvedRepeating->isNotEmpty() ? 'mt-6' : '' }}">
                            <p class="text-xs font-semibold text-slate uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate inline-block"></span>
                                Standalone ({{ $approvedStandalone->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($approvedStandalone as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                </div>

                {{-- Rejected tab --}}
                <div x-show="activeTab === 'rejected'">
                    @php
                        $rejectedRepeating  = $rejected->filter(fn($v) => ($v->occurrences ?: 1) > 1);
                        $rejectedStandalone = $rejected->filter(fn($v) => ($v->occurrences ?: 1) <= 1);
                    @endphp
                    @if($rejected->isEmpty())
                    <p class="text-sm text-muted text-center py-12">No rejected fields.</p>
                    @else
                        @if($rejectedRepeating->isNotEmpty())
                        <div class="mb-2">
                            <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                                Repeating ({{ $rejectedRepeating->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($rejectedRepeating as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                        @if($rejectedStandalone->isNotEmpty())
                        <div class="{{ $rejectedRepeating->isNotEmpty() ? 'mt-6' : '' }}">
                            <p class="text-xs font-semibold text-slate uppercase tracking-wide mb-3 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-slate inline-block"></span>
                                Standalone ({{ $rejectedStandalone->count() }})
                            </p>
                            <div class="space-y-3">
                                @foreach($rejectedStandalone as $var)
                                @include('partials.variable-card', ['var' => $var, 'template' => $template])
                                @endforeach
                            </div>
                        </div>
                        @endif
                    @endif
                </div>

                {{-- All tab --}}
                <div x-show="activeTab === 'all'">
                    @php $allVars = $pending->concat($approved)->concat($rejected); @endphp
                    @php
                        $allRepeating  = $allVars->filter(fn($v) => ($v->occurrences ?: 1) > 1);
                        $allStandalone = $allVars->filter(fn($v) => ($v->occurrences ?: 1) <= 1);
                    @endphp
                    @if($allRepeating->isNotEmpty())
                    <div class="mb-2">
                        <p class="text-xs font-semibold text-primary uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary inline-block"></span>
                            Repeating ({{ $allRepeating->count() }})
                        </p>
                        <div class="space-y-3">
                            @foreach($allRepeating as $var)
                            @include('partials.variable-card', ['var' => $var, 'template' => $template])
                            @endforeach
                        </div>
                    </div>
                    @endif
                    @if($allStandalone->isNotEmpty())
                    <div class="{{ $allRepeating->isNotEmpty() ? 'mt-6' : '' }}">
                        <p class="text-xs font-semibold text-slate uppercase tracking-wide mb-3 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-slate inline-block"></span>
                            Standalone ({{ $allStandalone->count() }})
                        </p>
                        <div class="space-y-3">
                            @foreach($allStandalone as $var)
                            @include('partials.variable-card', ['var' => $var, 'template' => $template])
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

            </div>
        </div>

        {{-- ── Right: Score + guidance ────────────────────────── --}}
        <div class="w-80 flex-shrink-0 border-l border-line bg-white overflow-y-auto p-6 space-y-5 hidden lg:block">

            {{-- Readiness score --}}
            <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-6 text-white">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Template Readiness</h3>
                    <div class="flex items-center gap-2">
                        <x-icon name="trending-up" class="w-5 h-5" />
                        <span class="text-3xl font-bold">{{ $readiness }}%</span>
                    </div>
                </div>
                <div class="w-full h-2 bg-white/20 rounded-full overflow-hidden mb-4">
                    <div class="h-full bg-white rounded-full transition-all duration-500"
                         style="width:{{ $readiness }}%"></div>
                </div>
                <ul class="space-y-2 text-sm text-white/90">
                    <li class="flex items-center gap-2">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        {{ $approved->count() + $pending->count() + $rejected->count() }} fields detected
                    </li>
                    <li class="flex items-center gap-2">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        {{ $approved->count() }} fields approved
                    </li>
                    @if($pending->count() > 0)
                    <li class="flex items-center gap-2">
                        <div class="w-4 h-4 rounded-full border-2 border-white flex-shrink-0"></div>
                        {{ $pending->count() }} pending review
                    </li>
                    @endif
                    @if($rejected->count() > 0)
                    <li class="flex items-center gap-2 text-white/70">
                        <x-icon name="x" class="w-4 h-4" />
                        {{ $rejected->count() }} rejected
                    </li>
                    @endif
                </ul>
            </div>

            {{-- CTA --}}
            @if($approved->count() > 0)
            <a href="{{ route('fillable-form', $template->id) }}"
               class="flex items-center justify-center gap-2 w-full bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors">
                <x-icon name="sparkles" class="w-5 h-5" />
                Generate Fillable Form
            </a>
            @elseif($rejected->count() > 0 && $pending->count() === 0)
            {{-- All fields rejected — guide the user --}}
            <div class="p-4 bg-warning/10 border border-warning/30 rounded-xl text-sm">
                <p class="font-semibold text-navy mb-1 flex items-center gap-2">
                    <x-icon name="alert-circle" class="w-4 h-4 text-warning" />
                    All fields rejected
                </p>
                <p class="text-xs text-slate">Switch to the <strong>Rejected</strong> tab and approve at least one field to generate a form.</p>
            </div>
            @else
            <div class="flex items-center justify-center gap-2 w-full bg-line text-muted py-3 rounded-xl font-semibold text-sm cursor-not-allowed">
                <x-icon name="sparkles" class="w-5 h-5" />
                Approve fields first
            </div>
            @endif

            <a href="{{ route('automation-map', $template->id) }}"
               class="flex items-center justify-center gap-2 w-full border-2 border-line text-navy py-3 rounded-xl font-medium text-sm hover:border-primary hover:bg-blue-soft transition-all">
                <x-icon name="arrow-left" class="w-4 h-4" />
                Back to Automation Map
            </a>

            {{-- Loopi tip --}}
            <div class="bg-blue-soft rounded-2xl p-4 border border-line">
                <div class="flex gap-3">
                    <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi"
                         class="w-14 h-14 object-contain flex-shrink-0">
                    <div class="text-sm text-slate">
                        <p class="font-medium text-navy mb-1">Loopi's Tip</p>
                        <p>Review the fields below. Accept the ones that look right, and I'll create a fillable form for them.</p>
                    </div>
                </div>
            </div>

            {{-- Smart Tools teaser --}}
            <div class="bg-white rounded-2xl border border-line p-4">
                <h3 class="text-sm font-semibold text-navy mb-3 flex items-center gap-2">
                    <x-icon name="sparkles" class="w-4 h-4 text-primary" />
                    Smart Tools
                    <span class="ml-auto text-xs text-warning font-medium">Pro</span>
                </h3>
                <div class="space-y-2 text-xs text-slate">
                    <p>• Smart Rules — show/hide fields conditionally</p>
                    <p>• Auto-Calculated Fields — totals, balances</p>
                    <p>• Repeating Rows — tables and line items</p>
                    <p>• QR Code Generator</p>
                </div>
                <a href="#" class="mt-3 block text-center text-xs text-primary hover:underline font-medium">
                    Upgrade to Pro to unlock →
                </a>
            </div>

        </div>

    </div>
</div>
</x-layouts.app>
