<x-layouts.app title="{{ $template->name }} — Template Editor — RepetitiveDocs">
<div class="flex flex-col h-full" x-data="{ activeTab: 'pending' }">

    {{-- ── Top bar ────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-line px-6 py-4 flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3 min-w-0">
            <a href="{{ route('dashboard') }}" class="text-muted hover:text-navy transition-colors flex-shrink-0">
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

            {{-- Tab bar --}}
            <div class="bg-white border-b border-line px-6 pt-4">
                <div class="flex gap-1 bg-blue-soft p-1 rounded-xl w-fit">
                    @foreach([
                        ['key'=>'pending',  'label'=>'Pending',  'count'=>$pending->count(),  'color'=>'bg-primary'],
                        ['key'=>'approved', 'label'=>'Approved', 'count'=>$approved->count(), 'color'=>'bg-success'],
                        ['key'=>'rejected', 'label'=>'Rejected', 'count'=>$rejected->count(), 'color'=>'bg-danger'],
                        ['key'=>'all',      'label'=>'All',      'count'=>$pending->count()+$approved->count()+$rejected->count(), 'color'=>'bg-slate'],
                    ] as $tab)
                    <button @click="activeTab = '{{ $tab['key'] }}'"
                            :class="activeTab === '{{ $tab['key'] }}' ? 'bg-white text-navy shadow-sm' : 'text-slate hover:text-navy'"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all flex items-center gap-2">
                        {{ $tab['label'] }}
                        @if($tab['count'] > 0)
                        <span class="px-1.5 py-0.5 {{ $tab['color'] }} text-white rounded-full text-xs leading-none">
                            {{ $tab['count'] }}
                        </span>
                        @endif
                    </button>
                    @endforeach
                </div>

                {{-- Approve all pending --}}
                @if($pending->count() > 0)
                <div class="pb-3 mt-3 flex items-center justify-between">
                    <p class="text-xs text-muted">{{ $pending->count() }} fields waiting for review</p>
                    <form method="POST" action="{{ route('templates.editor.approve-all', $template->id) }}">
                        @csrf
                        <button type="submit" class="text-xs text-primary hover:underline font-medium">
                            Accept all pending →
                        </button>
                    </form>
                </div>
                @endif
            </div>

            {{-- Variable cards --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-3">

                {{-- Pending tab --}}
                <div x-show="activeTab === 'pending'">
                    @forelse($pending as $var)
                    @include('partials.variable-card', ['var' => $var, 'template' => $template])
                    @empty
                    <div class="flex flex-col items-center py-16 text-center">
                        <x-icon name="check-circle" class="w-12 h-12 text-success mb-4" />
                        <p class="font-semibold text-navy">All fields reviewed!</p>
                        <p class="text-sm text-slate mt-1">No pending fields left.</p>
                    </div>
                    @endforelse
                </div>

                {{-- Approved tab --}}
                <div x-show="activeTab === 'approved'">
                    @forelse($approved as $var)
                    @include('partials.variable-card', ['var' => $var, 'template' => $template])
                    @empty
                    <p class="text-sm text-muted text-center py-12">No approved fields yet.</p>
                    @endforelse
                </div>

                {{-- Rejected tab --}}
                <div x-show="activeTab === 'rejected'">
                    @forelse($rejected as $var)
                    @include('partials.variable-card', ['var' => $var, 'template' => $template])
                    @empty
                    <p class="text-sm text-muted text-center py-12">No rejected fields.</p>
                    @endforelse
                </div>

                {{-- All tab --}}
                <div x-show="activeTab === 'all'">
                    @foreach($pending->concat($approved)->concat($rejected) as $var)
                    @include('partials.variable-card', ['var' => $var, 'template' => $template])
                    @endforeach
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
                        {{ $pending->count() }} fields need review
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
