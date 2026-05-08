<x-layouts.auth title="RepetitiveDocs — Upload once. Personalize forever.">
<div class="min-h-screen bg-white overflow-x-hidden" x-data="{ mobileMenuOpen: false }">

    {{-- ── Header ────────────────────────────────────────────────── --}}
    <header class="border-b border-line bg-white/80 backdrop-blur-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 py-4">
            <div class="flex items-center justify-between">
                <img src="{{ asset('images/logo-primary.png') }}" alt="RepetitiveDocs" class="h-10 w-auto object-contain">

                <nav class="hidden md:flex items-center gap-8">
                    <a href="#how-it-works" class="text-sm text-slate hover:text-navy transition-colors">How It Works</a>
                    <a href="#use-cases"    class="text-sm text-slate hover:text-navy transition-colors">Templates</a>
                    <a href="#pricing"      class="text-sm text-slate hover:text-navy transition-colors">Pricing</a>
                    <a href="#security"     class="text-sm text-slate hover:text-navy transition-colors">Security</a>
                </nav>

                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}" class="hidden md:block text-sm text-slate hover:text-navy transition-colors font-medium">Sign In</a>
                    <a href="{{ route('register') }}" class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-primary-dark transition-colors">
                        <x-icon name="upload" class="w-4 h-4" />
                        Upload First Document
                    </a>
                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 hover:bg-blue-soft rounded-lg transition-colors">
                        <x-icon x-show="!mobileMenuOpen" name="menu" class="w-6 h-6 text-slate" />
                        <x-icon x-show="mobileMenuOpen"  name="x"    class="w-6 h-6 text-slate" />
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-show="mobileMenuOpen" x-cloak class="md:hidden mt-4 pb-4 border-t border-line pt-4">
                <nav class="flex flex-col gap-3">
                    <a href="#how-it-works" @click="mobileMenuOpen=false" class="text-sm text-slate hover:text-navy transition-colors">How It Works</a>
                    <a href="#use-cases"    @click="mobileMenuOpen=false" class="text-sm text-slate hover:text-navy transition-colors">Templates</a>
                    <a href="#pricing"      @click="mobileMenuOpen=false" class="text-sm text-slate hover:text-navy transition-colors">Pricing</a>
                    <a href="#security"     @click="mobileMenuOpen=false" class="text-sm text-slate hover:text-navy transition-colors">Security</a>
                    <a href="{{ route('login') }}" class="text-sm text-slate hover:text-navy transition-colors font-medium">Sign In</a>
                </nav>
            </div>
        </div>
    </header>

    {{-- ── Hero ──────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden">
        {{-- BG grid --}}
        <div class="absolute inset-0 bg-[linear-gradient(to_right,#E6ECF5_1px,transparent_1px),linear-gradient(to_bottom,#E6ECF5_1px,transparent_1px)] bg-[size:4rem_4rem] opacity-30"></div>
        <div class="absolute top-0 right-0 w-[600px] h-[600px] bg-primary/10 rounded-full blur-[120px]"></div>

        <div class="relative max-w-7xl mx-auto px-6 py-12 lg:py-16">
            <div class="grid lg:grid-cols-2 gap-12 items-center">

                {{-- Left --}}
                <div class="relative z-10">
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-blue-light border border-primary/20 rounded-full mb-6">
                        <x-icon name="sparkles" class="w-4 h-4 text-primary" />
                        <span class="text-sm font-medium text-primary">AI document automation for repetitive work</span>
                    </div>

                    <h1 class="text-5xl lg:text-6xl font-bold text-navy mb-6 leading-tight">
                        Create repetitive documents in minutes, not hours.
                    </h1>

                    <p class="text-xl text-slate mb-8 leading-relaxed">
                        Upload a PDF or Word file. Loopi finds what changes, turns it into a reusable template, and helps you generate personalized documents one by one or in bulk.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 bg-primary text-white px-6 py-3.5 rounded-xl font-semibold hover:bg-primary-dark transition-colors shadow-lg">
                            <x-icon name="upload" class="w-5 h-5" />
                            Upload Your First Document
                        </a>
                        <a href="#how-it-works" class="inline-flex items-center justify-center gap-2 border-2 border-line text-navy px-6 py-3.5 rounded-xl font-semibold hover:border-primary hover:bg-blue-soft transition-all">
                            <x-icon name="eye" class="w-5 h-5" />
                            See How It Works
                        </a>
                    </div>

                    <div class="flex flex-wrap gap-6 text-sm text-slate">
                        @foreach(['PDF & DOCX supported', 'AI variable detection', 'Bulk generation'] as $badge)
                        <div class="flex items-center gap-2">
                            <x-icon name="check-circle" class="w-5 h-5 text-success" />
                            <span>{{ $badge }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Right — Loopi visual --}}
                <div class="relative h-[500px] lg:h-[600px]">
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 bg-primary/20 blur-3xl -z-10"></div>

                    {{-- Floating cards --}}
                    <div class="absolute top-8 left-4 lg:left-0 bg-white rounded-2xl p-4 shadow-xl border border-line animate-float z-30">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center"><x-icon name="upload" class="w-5 h-5 text-primary" /></div>
                            <div><p class="text-xs text-slate">Upload</p><p class="text-sm font-semibold text-navy">PDF/DOCX</p></div>
                        </div>
                    </div>
                    <div class="absolute top-24 right-4 lg:right-0 bg-white rounded-2xl p-4 shadow-xl border border-line animate-float z-30" style="animation-delay:.5s">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center"><x-icon name="sparkles" class="w-5 h-5 text-success" /></div>
                            <div><p class="text-xs text-slate">AI Found</p><p class="text-sm font-semibold text-navy">14 Fields</p></div>
                        </div>
                    </div>
                    <div class="absolute bottom-32 left-4 lg:left-0 bg-white rounded-2xl p-4 shadow-xl border border-line animate-float z-30" style="animation-delay:1s">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center"><x-icon name="trending-up" class="w-5 h-5 text-primary" /></div>
                            <div><p class="text-xs text-slate">Readiness</p><p class="text-sm font-semibold text-navy">82%</p></div>
                        </div>
                    </div>
                    <div class="absolute bottom-12 right-8 lg:right-12 bg-white rounded-2xl p-4 shadow-xl border border-line animate-float z-30" style="animation-delay:1.5s">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center"><x-icon name="file-text" class="w-5 h-5 text-success" /></div>
                            <div><p class="text-xs text-slate">Generate</p><p class="text-sm font-semibold text-navy">PDF Ready</p></div>
                        </div>
                    </div>

                    {{-- Variable tags --}}
                    <div class="absolute top-1/3 left-1/4 px-3 py-1 bg-blue-light border border-primary/30 rounded-lg text-xs font-mono text-primary z-30">{ Client Name }</div>
                    <div class="absolute top-1/2 right-1/4 px-3 py-1 bg-blue-light border border-primary/30 rounded-lg text-xs font-mono text-primary z-30">{ Amount }</div>

                    {{-- Loopi --}}
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10">
                        <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-72 h-72 lg:w-96 lg:h-96 object-contain" style="mix-blend-mode:multiply">
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- ── Upload Preview ───────────────────────────────────────── --}}
    <section class="max-w-5xl mx-auto px-6 py-12">
        <div class="text-center mb-8">
            <h2 class="text-3xl font-bold text-navy mb-3">Start with the document you already have</h2>
            <p class="text-lg text-slate">Loopi will scan your document and suggest fields to automate</p>
        </div>
        <a href="{{ route('register') }}" class="block border-2 border-dashed border-line rounded-3xl p-12 hover:border-primary hover:bg-blue-soft transition-all group">
            <div class="text-center">
                <div class="w-20 h-20 bg-primary/10 rounded-3xl flex items-center justify-center mx-auto mb-4 group-hover:bg-primary/20 transition-colors">
                    <x-icon name="upload" class="w-10 h-10 text-primary" />
                </div>
                <p class="text-lg font-semibold text-navy mb-2">Drop your PDF or Word file here, or browse</p>
                <p class="text-sm text-slate mb-6">Loopi will scan your document and suggest fields to automate</p>
                <div class="inline-flex items-center gap-4 text-xs text-slate bg-blue-soft px-4 py-2 rounded-lg">
                    <span><strong class="text-warning">Free:</strong> 5 MB</span>
                    <span><strong class="text-primary">Starter:</strong> 25 MB</span>
                    <span><strong class="text-primary">Pro:</strong> 100 MB</span>
                    <span><strong class="text-primary">Business:</strong> 250 MB</span>
                </div>
            </div>
        </a>
    </section>

    {{-- ── Why RepetitiveDocs ───────────────────────────────────── --}}
    <section class="relative py-12 bg-canvas">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-navy mb-4">Why RepetitiveDocs?</h2>
                <p class="text-lg text-slate">Built for anyone who creates the same document over and over</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach([
                    ['icon'=>'sparkles',  'title'=>'AI Finds What Changes',       'desc'=>'Names, dates, amounts, addresses, and repeated text are detected automatically.'],
                    ['icon'=>'check-circle','title'=>'No More Copy-Paste Errors', 'desc'=>'Find similar text and apply one variable across every matching mention.'],
                    ['icon'=>'database',  'title'=>'Generate One or Hundreds',    'desc'=>'Fill a form for one document or upload a spreadsheet for bulk generation.'],
                    ['icon'=>'lock',      'title'=>'Share Secure Portals',        'desc'=>'Create public links on Free or password-protected portals on Starter and above.'],
                ] as $f)
                <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg transition-shadow">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                        <x-icon name="{{ $f['icon'] }}" class="w-6 h-6 text-primary" />
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">{{ $f['title'] }}</h3>
                    <p class="text-sm text-slate">{{ $f['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── How It Works ─────────────────────────────────────────── --}}
    <section id="how-it-works" class="max-w-7xl mx-auto px-6 py-12">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-navy mb-4">How Loopi turns old documents into reusable templates</h2>
            <p class="text-lg text-slate">Four simple steps to automate your document workflow</p>
        </div>
        <div class="grid md:grid-cols-4 gap-8">
            @foreach([
                ['step'=>'1','icon'=>'upload',      'title'=>'Upload your document',   'desc'=>'Start with an existing PDF or Word file'],
                ['step'=>'2','icon'=>'sparkles',    'title'=>'Let AI find variables',  'desc'=>'Loopi detects fields like names, dates, amounts, organizations, and repeated text'],
                ['step'=>'3','icon'=>'file-check',  'title'=>'Review your template',   'desc'=>'Accept, edit, or reject suggested fields. Run AI Preflight before generating'],
                ['step'=>'4','icon'=>'send',        'title'=>'Generate and share',     'desc'=>'Generate a single document, upload CSV/XLSX for bulk, or share a portal link'],
            ] as $item)
            <div class="relative">
                <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg transition-shadow">
                    <div class="w-14 h-14 bg-gradient-to-br from-primary to-primary-dark rounded-xl flex items-center justify-center text-white font-bold text-2xl mb-4">{{ $item['step'] }}</div>
                    <div class="w-12 h-12 bg-blue-soft rounded-xl flex items-center justify-center mb-4">
                        <x-icon name="{{ $item['icon'] }}" class="w-6 h-6 text-primary" />
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">{{ $item['title'] }}</h3>
                    <p class="text-sm text-slate">{{ $item['desc'] }}</p>
                </div>
                @if($item['step'] !== '4')
                <div class="hidden md:flex absolute top-1/2 -right-4 items-center justify-center w-8 z-10">
                    <x-icon name="arrow-right" class="w-5 h-5 text-primary" />
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </section>

    {{-- ── Use Cases ────────────────────────────────────────────── --}}
    <section id="use-cases" class="bg-canvas py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-navy mb-4">Built for the documents you keep repeating</h2>
                <p class="text-lg text-slate">From business proposals to certificates, RepetitiveDocs handles it all</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach([
                    ['icon'=>'file-text',  'color'=>'#2F6BFF','title'=>'Business Proposals',  'desc'=>'Client name, project amount, scope, dates, signatory, address'],
                    ['icon'=>'file-check', 'color'=>'#22C55E','title'=>'Service Agreements',  'desc'=>'Party names, services, rates, terms, start date, signatures'],
                    ['icon'=>'shield',     'color'=>'#FF7043','title'=>'Certificates',         'desc'=>'Recipient name, achievement, date, issuer, certificate number'],
                    ['icon'=>'team',       'color'=>'#2F6BFF','title'=>'HR Letters',           'desc'=>'Employee name, position, salary, start date, department'],
                    ['icon'=>'globe',      'color'=>'#22C55E','title'=>'Government Letters',   'desc'=>'LGU, official name, project details, amounts, approval dates'],
                    ['icon'=>'dollar-sign','color'=>'#FF7043','title'=>'Invoices',             'desc'=>'Client, items, quantities, prices, total, due date, terms'],
                    ['icon'=>'file-text',  'color'=>'#2F6BFF','title'=>'School Forms',         'desc'=>'Student name, grade, section, parent, emergency contacts'],
                    ['icon'=>'send',       'color'=>'#22C55E','title'=>'Client Onboarding',    'desc'=>'Client details, services, pricing, contacts, setup info'],
                ] as $u)
                <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg hover:border-primary/30 transition-all cursor-pointer">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center mb-4" style="background-color:{{ $u['color'] }}20">
                        <x-icon name="{{ $u['icon'] }}" class="w-6 h-6" style="color:{{ $u['color'] }}" />
                    </div>
                    <h3 class="text-lg font-semibold text-navy mb-2">{{ $u['title'] }}</h3>
                    <p class="text-sm text-slate">{{ $u['desc'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Bulk Generation ──────────────────────────────────────── --}}
    <section class="max-w-7xl mx-auto px-6 py-12">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-4xl font-bold text-navy mb-4">Generate hundreds from one spreadsheet</h2>
                <p class="text-lg text-slate mb-8">Upload CSV or XLSX, map columns to variables, preview sample rows, and download all documents in one ZIP.</p>
                <div class="space-y-4 mb-8">
                    @foreach([
                        ['icon'=>'upload',      'label'=>'Upload Spreadsheet'],
                        ['icon'=>'refresh',     'label'=>'Map Columns'],
                        ['icon'=>'check-circle','label'=>'Validate Data'],
                        ['icon'=>'eye',         'label'=>'Preview Samples'],
                        ['icon'=>'download',    'label'=>'Generate ZIP'],
                    ] as $step)
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center flex-shrink-0">
                            <x-icon name="{{ $step['icon'] }}" class="w-5 h-5 text-primary" />
                        </div>
                        <div class="flex-1 h-2 bg-line rounded-full"><div class="h-full w-full bg-primary rounded-full"></div></div>
                        <span class="text-sm font-medium text-navy min-w-[140px]">{{ $step['label'] }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="bg-warning/10 border border-warning/20 rounded-xl p-4">
                    <p class="text-sm text-warning font-medium">Bulk generation is available on Starter and above</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl p-8 border border-line shadow-lg">
                <div class="bg-blue-soft rounded-xl p-4 mb-4">
                    <div class="grid grid-cols-3 gap-2 text-xs font-semibold text-slate mb-2">
                        <span>Client Name</span><span>Amount</span><span>Date</span>
                    </div>
                    @foreach([1,2,3] as $row)
                    <div class="grid grid-cols-3 gap-2 text-xs text-navy py-2 border-t border-line">
                        <span>ABC Corp {{ $row }}</span>
                        <span>₱{{ number_format($row * 50000) }}</span>
                        <span>2026-05-{{ 10 + $row }}</span>
                    </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-center my-4">
                    <x-icon name="arrow-right" class="w-6 h-6 text-primary" />
                </div>
                <div class="bg-gradient-to-br from-primary to-primary-dark rounded-xl p-4 text-white">
                    <div class="flex items-center gap-3 mb-3">
                        <x-icon name="download" class="w-6 h-6" />
                        <span class="font-semibold">Download ZIP</span>
                    </div>
                    <p class="text-sm text-white/90">48 documents generated</p>
                    <p class="text-sm text-white/90">12.4 MB total</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ── Portal Section ───────────────────────────────────────── --}}
    <section class="bg-canvas py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-navy mb-4">Turn any template into a shareable form</h2>
                <p class="text-lg text-slate">Let recipients fill the fields themselves through a public form link or private password-protected portal</p>
            </div>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-2xl p-6 border border-line">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4"><x-icon name="link" class="w-6 h-6 text-primary" /></div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Public Link</h3>
                    <p class="text-sm text-slate mb-4">Anyone with the link can fill and submit the form</p>
                    <div class="bg-blue-soft rounded-lg p-3 text-xs font-mono text-primary break-all">repetitivedocs.com/p/abc123</div>
                </div>
                <div class="bg-white rounded-2xl p-6 border-2 border-primary/30">
                    <div class="w-12 h-12 bg-success/10 rounded-xl flex items-center justify-center mb-4"><x-icon name="lock" class="w-6 h-6 text-success" /></div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Password Protected</h3>
                    <p class="text-sm text-slate mb-4">Secure portal with password requirement (Starter+)</p>
                    <div class="bg-blue-soft rounded-lg p-3">
                        <div class="flex items-center gap-2 text-xs text-slate"><x-icon name="lock" class="w-4 h-4 text-success" /><span>Password required</span></div>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-6 border border-line">
                    <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4"><x-icon name="bar-chart" class="w-6 h-6 text-primary" /></div>
                    <h3 class="text-lg font-semibold text-navy mb-2">Analytics & Inbox</h3>
                    <p class="text-sm text-slate mb-4">Track views, submissions, and manage responses</p>
                    <div class="space-y-2 text-xs text-slate">
                        <div class="flex justify-between"><span>Views:</span><span class="font-semibold text-navy">234</span></div>
                        <div class="flex justify-between"><span>Submissions:</span><span class="font-semibold text-navy">48</span></div>
                    </div>
                </div>
            </div>
            <div class="mt-8 bg-warning/10 border border-warning/20 rounded-xl p-4 max-w-2xl mx-auto text-center">
                <p class="text-sm text-warning font-medium">Free links are public. Upgrade to Starter to add password protection.</p>
            </div>
        </div>
    </section>

    {{-- ── AI Preflight ─────────────────────────────────────────── --}}
    <section class="max-w-7xl mx-auto px-6 py-12">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-navy mb-4">Loopi checks before you generate</h2>
            <p class="text-lg text-slate">AI Preflight catches issues and suggests improvements</p>
        </div>
        <div class="grid md:grid-cols-2 gap-6">
            @foreach([
                ['type'=>'success','icon'=>'check-circle','title'=>'Field consistency verified',    'desc'=>'Client name appears on Page 1 and Page 4, both marked as variables.'],
                ['type'=>'warning','icon'=>'alert-circle','title'=>'Currency formatting needed',    'desc'=>'This amount should use currency formatting for consistency.'],
                ['type'=>'info',   'icon'=>'alert-circle','title'=>'Make this field required?',    'desc'=>'This date looks like a deadline. Consider making it required.'],
                ['type'=>'info',   'icon'=>'alert-circle','title'=>'Signature block detected',     'desc'=>'This signature block may need signer details like name and title.'],
            ] as $check)
            @php
                $colors = [
                    'success' => ['border'=>'border-success/20','bg'=>'bg-success/5','icon-bg'=>'bg-success/10','icon-color'=>'text-success'],
                    'warning' => ['border'=>'border-warning/20','bg'=>'bg-warning/5','icon-bg'=>'bg-warning/10','icon-color'=>'text-warning'],
                    'info'    => ['border'=>'border-primary/20','bg'=>'bg-primary/5','icon-bg'=>'bg-primary/10','icon-color'=>'text-primary'],
                ];
                $c = $colors[$check['type']];
            @endphp
            <div class="bg-white rounded-2xl p-6 border-2 {{ $c['border'] }} {{ $c['bg'] }}">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 {{ $c['icon-bg'] }} rounded-xl flex items-center justify-center flex-shrink-0">
                        <x-icon name="{{ $check['icon'] }}" class="w-6 h-6 {{ $c['icon-color'] }}" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-navy mb-1">{{ $check['title'] }}</h3>
                        <p class="text-sm text-slate">{{ $check['desc'] }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ── Pricing Preview ──────────────────────────────────────── --}}
    <section id="pricing" class="bg-canvas py-12">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-navy mb-4">Start free. Upgrade when your documents need privacy, volume, or teamwork.</h2>
                <p class="text-lg text-slate">Flexible pricing that grows with your needs</p>
            </div>
            <div class="grid md:grid-cols-4 gap-6 items-start">
                @foreach([
                    ['name'=>'Free',     'price'=>'₱0',     'period'=>'forever',  'badge'=>null,               'popular'=>false, 'cta'=>'Get Started',
                     'features'=>['3 templates','5 documents/month','10 AI credits','5 MB files','Public links only']],
                    ['name'=>'Starter',  'price'=>'₱499',   'period'=>'/month',   'badge'=>'Best first upgrade','popular'=>false, 'cta'=>'Start Trial',
                     'features'=>['10 templates','100 documents/month','50 AI credits','25 MB files','Password portals','Bulk up to 50 rows']],
                    ['name'=>'Pro',      'price'=>'₱1,499', 'period'=>'/month',   'badge'=>'Most Popular',      'popular'=>true,  'cta'=>'Start Trial',
                     'features'=>['50 templates','500 documents/month','300 AI credits','100 MB files','Brand kit','Version history']],
                    ['name'=>'Business', 'price'=>'₱3,999', 'period'=>'/month',   'badge'=>null,               'popular'=>false, 'cta'=>'Start Trial',
                     'features'=>['200 templates','2,000 documents/month','1,500 AI credits','250 MB files','Team workspace','Audit logs']],
                ] as $plan)
                <div class="bg-white rounded-2xl p-6 border-2 relative {{ $plan['popular'] ? 'border-primary shadow-lg scale-105' : 'border-line' }}">
                    @if($plan['badge'])
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 px-3 py-1 bg-primary text-white text-xs font-semibold rounded-full whitespace-nowrap">{{ $plan['badge'] }}</div>
                    @endif
                    <div class="text-center mb-6">
                        <h3 class="text-lg font-semibold text-navy mb-2">{{ $plan['name'] }}</h3>
                        <div class="flex items-baseline justify-center gap-1 mb-1">
                            <span class="text-3xl font-bold text-navy">{{ $plan['price'] }}</span>
                            <span class="text-sm text-slate">{{ $plan['period'] }}</span>
                        </div>
                    </div>
                    <ul class="space-y-3 mb-6">
                        @foreach($plan['features'] as $feature)
                        <li class="flex items-start gap-2 text-sm text-slate">
                            <x-icon name="check-circle" class="w-4 h-4 text-success flex-shrink-0 mt-0.5" />
                            <span>{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('register') }}" class="block w-full text-center py-2.5 rounded-xl font-semibold text-sm transition-colors {{ $plan['popular'] ? 'bg-primary text-white hover:bg-primary-dark' : 'border-2 border-line text-navy hover:border-primary hover:bg-blue-soft' }}">
                        {{ $plan['cta'] }}
                    </a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── Security ─────────────────────────────────────────────── --}}
    <section id="security" class="max-w-7xl mx-auto px-6 py-12">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-navy mb-4">Designed for documents that matter</h2>
            <p class="text-lg text-slate">Enterprise-grade security and privacy controls</p>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach([
                ['icon'=>'shield',      'title'=>'Server-side AI processing',    'desc'=>'Your documents are processed securely on our servers, never shared with third parties'],
                ['icon'=>'lock',        'title'=>'Password-protected portals',   'desc'=>'Add password protection to portals on Starter and above'],
                ['icon'=>'file-check',  'title'=>'Private files by default',     'desc'=>'All uploads and generated documents are private to your account'],
                ['icon'=>'clock',       'title'=>'Retention policies',           'desc'=>'Free accounts have no long-term storage. Paid plans have configurable retention'],
                ['icon'=>'bar-chart',   'title'=>'Audit logs',                   'desc'=>'Track all activity in your workspace with audit logs on Business plans'],
                ['icon'=>'team',        'title'=>'Workspace isolation',          'desc'=>'Each workspace is completely isolated with role-based access controls'],
            ] as $f)
            <div class="bg-white rounded-2xl p-6 border border-line hover:shadow-lg transition-shadow">
                <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center mb-4">
                    <x-icon name="{{ $f['icon'] }}" class="w-6 h-6 text-primary" />
                </div>
                <h3 class="text-lg font-semibold text-navy mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-slate">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ── Final CTA ────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden py-20">
        <div class="absolute inset-0 bg-gradient-to-br from-primary to-primary-dark"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:4rem_4rem]"></div>
        <div class="relative max-w-4xl mx-auto px-6 text-center">
            <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-40 h-40 object-contain mx-auto mb-8" style="mix-blend-mode:multiply;filter:brightness(0) invert(1)">
            <h2 class="text-4xl font-bold text-white mb-4">Ready to stop repeating the same document work?</h2>
            <p class="text-xl text-white/90 mb-8">Upload your first document and let Loopi find what changes</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 bg-white text-primary px-6 py-3.5 rounded-xl font-semibold hover:bg-blue-soft transition-colors">
                    <x-icon name="upload" class="w-5 h-5" />
                    Upload Your First Document
                </a>
                <a href="#pricing" class="inline-flex items-center justify-center gap-2 bg-white/10 border border-white/20 text-white px-6 py-3.5 rounded-xl font-semibold hover:bg-white/20 transition-colors">
                    View Pricing
                </a>
            </div>
        </div>
    </section>

    {{-- ── Footer ───────────────────────────────────────────────── --}}
    <footer class="border-t border-line bg-white">
        <div class="max-w-7xl mx-auto px-6 py-12">
            <div class="grid md:grid-cols-5 gap-8 mb-12">
                <div class="md:col-span-2">
                    <img src="{{ asset('images/logo-primary.png') }}" alt="RepetitiveDocs" class="h-10 w-auto object-contain mb-4">
                    <p class="text-sm text-slate">Upload once. Personalize forever. Turn repetitive documents into smart templates with AI.</p>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-navy mb-4">Product</h4>
                    <ul class="space-y-2 text-sm text-slate">
                        <li><a href="#how-it-works" class="hover:text-primary transition-colors">How It Works</a></li>
                        <li><a href="#use-cases"    class="hover:text-primary transition-colors">Use Cases</a></li>
                        <li><a href="#pricing"      class="hover:text-primary transition-colors">Pricing</a></li>
                        <li><a href="#security"     class="hover:text-primary transition-colors">Security</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-navy mb-4">Company</h4>
                    <ul class="space-y-2 text-sm text-slate">
                        <li><a href="#" class="hover:text-primary transition-colors">About Us</a></li>
                        <li><a href="#" class="hover:text-primary transition-colors">Blog</a></li>
                        <li><a href="#" class="hover:text-primary transition-colors">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-navy mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm text-slate">
                        <li><a href="#" class="hover:text-primary transition-colors">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-primary transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-primary transition-colors">Cookie Policy</a></li>
                    </ul>
                </div>
            </div>
            <div class="pt-8 border-t border-line flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-sm text-slate">© 2026 RepetitiveDocs.com. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="text-slate hover:text-primary transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84"/></svg>
                    </a>
                    <a href="#" class="text-slate hover:text-primary transition-colors">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

</div>
</x-layouts.auth>
