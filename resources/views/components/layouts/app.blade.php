<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'RepetitiveDocs' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-canvas antialiased" x-data="{ mobileNavOpen: false }">

<div class="flex h-screen overflow-hidden bg-canvas">

    {{-- ── Left Sidebar ─────────────────────────────────────────── --}}
    <aside class="w-64 bg-white border-r border-line flex flex-col flex-shrink-0 hidden lg:flex">

        {{-- Logo --}}
        <div class="p-6 border-b border-line">
            <a href="{{ route('dashboard') }}">
                <img src="{{ asset('images/logo-compact.png') }}" alt="RepetitiveDocs" class="h-10 w-auto object-contain">
            </a>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            @php
                $navItems = [
                    ['route' => 'dashboard',     'icon' => 'dashboard',   'label' => 'Dashboard'],
                    ['route' => 'template-gallery','icon' => 'layers',    'label' => 'Template Gallery'],
                    ['route' => 'templates',      'icon' => 'file-text',  'label' => 'My Templates'],
                    ['route' => 'upload',         'icon' => 'upload',     'label' => 'Upload Document'],
                    ['route' => 'bulk.upload',    'icon' => 'database',   'label' => 'Bulk Generate'],
                    ['route' => 'portals',        'icon' => 'link',       'label' => 'Portals'],
                    ['route' => 'history',        'icon' => 'history',    'label' => 'Generation History'],
                    ['route' => 'brand-kit',      'icon' => 'palette',    'label' => 'Brand Kit'],
                    ['route' => 'settings',       'icon' => 'settings',   'label' => 'Settings'],
                    ['route' => 'pricing',        'icon' => 'credit-card','label' => 'Billing / Upgrade'],
                ];
            @endphp

            @foreach ($navItems as $item)
                @if (Route::has($item['route']))
                    <a href="{{ route($item['route']) }}"
                       class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 text-sm font-medium
                              {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*')
                                 ? 'bg-primary text-white shadow-md'
                                 : 'text-slate hover:bg-blue-soft hover:text-navy' }}">
                        <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 flex-shrink-0" />
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </nav>

        {{-- Upgrade card (shown only on Free plan) --}}
        @if (($currentWorkspace?->plan?->slug ?? 'free') === 'free')
        <div class="p-4">
            <div class="bg-gradient-to-br from-primary to-primary-dark rounded-2xl p-4 text-white">
                <div class="flex items-center gap-2 mb-2">
                    <x-icon name="arrow-up-circle" class="w-5 h-5" />
                    <h3 class="font-semibold text-sm">Upgrade to Starter</h3>
                </div>
                <p class="text-xs text-white/80 mb-3">Unlock bulk generation and password-protected portals</p>
                @if (Route::has('pricing'))
                    <a href="#" class="block w-full text-center text-xs font-semibold bg-white text-primary rounded-xl py-2 hover:bg-blue-soft transition-colors">
                        View Plans
                    </a>
                @endif
            </div>
        </div>
        @endif

    </aside>

    {{-- ── Main Content ─────────────────────────────────────────── --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Top Bar --}}
        <header class="bg-white border-b border-line px-6 py-4 flex-shrink-0">
            <div class="flex items-center justify-between">

                {{-- Left: greeting + search --}}
                <div class="flex items-center gap-4 flex-1">
                    {{-- Mobile hamburger --}}
                    <button @click="mobileNavOpen = !mobileNavOpen" class="lg:hidden p-2 rounded-xl hover:bg-blue-soft transition-colors">
                        <svg class="w-6 h-6 text-slate" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <span class="text-lg font-semibold text-navy hidden sm:block">
                        Hi, {{ auth()->user()->name ? explode(' ', auth()->user()->name)[0] : 'there' }}!
                    </span>

                    @if (Route::has('search'))
                    <div class="flex-1 max-w-md hidden sm:block">
                        <a href="{{ route('search') }}" class="w-full relative group flex items-center">
                            <x-icon name="search" class="absolute left-3 w-5 h-5 text-muted" />
                            <div class="w-full pl-10 pr-20 py-2 border border-line rounded-xl bg-canvas text-muted text-sm group-hover:border-primary transition-colors">
                                Search templates, documents...
                            </div>
                            <kbd class="absolute right-3 px-2 py-1 bg-white border border-line rounded text-xs text-slate font-mono">⌘K</kbd>
                        </a>
                    </div>
                    @endif
                </div>

                {{-- Right: credits + actions --}}
                <div class="flex items-center gap-3">
                    @if (Route::has('ai-credits'))
                    <a href="{{ route('ai-credits') }}" class="hidden sm:flex items-center gap-2 px-3 py-2 bg-blue-soft rounded-xl hover:bg-blue-light transition-colors">
                        <x-icon name="coins" class="w-4 h-4 text-primary" />
                        <span class="text-sm font-semibold text-navy">
                            {{ $currentWorkspace?->ai_credits_remaining ?? 0 }} credits
                        </span>
                    </a>
                    @endif

                    @if (Route::has('upload'))
                    <a href="{{ route('upload') }}" class="hidden sm:flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-xl hover:bg-primary-dark transition-colors text-sm font-medium">
                        <x-icon name="upload" class="w-4 h-4" />
                        Upload
                    </a>
                    @endif

                    @if (Route::has('notifications'))
                    <a href="{{ route('notifications') }}" class="relative p-2 rounded-xl hover:bg-blue-soft transition-colors">
                        <x-icon name="bell" class="w-5 h-5 text-slate" />
                        {{-- Uncomment when notification count is wired up --}}
                        {{-- <span class="absolute top-1 right-1 w-2 h-2 bg-warning rounded-full"></span> --}}
                    </a>
                    @endif

                    {{-- User menu --}}
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="w-9 h-9 rounded-full bg-primary text-white flex items-center justify-center font-semibold text-sm hover:bg-primary-dark transition-colors">
                            {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="absolute right-0 top-11 w-52 bg-white rounded-2xl border border-line shadow-lg py-2 z-50">
                            <div class="px-4 py-2 border-b border-line">
                                <p class="text-sm font-semibold text-navy truncate">{{ auth()->user()->name }}</p>
                                <p class="text-xs text-slate truncate">{{ auth()->user()->email }}</p>
                            </div>
                            @if (Route::has('settings'))
                            <a href="{{ route('settings') }}" class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate hover:bg-blue-soft hover:text-navy transition-colors">
                                <x-icon name="settings" class="w-4 h-4" />
                                Settings
                            </a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-danger hover:bg-red-50 transition-colors">
                                    <x-icon name="log-out" class="w-4 h-4" />
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        {{-- Page Content --}}
        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>

    </div>
</div>

{{-- Mobile nav overlay --}}
<div x-show="mobileNavOpen" @click="mobileNavOpen = false" x-cloak
     class="fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

<div x-show="mobileNavOpen" x-cloak
     class="fixed inset-y-0 left-0 w-64 bg-white border-r border-line z-50 flex flex-col lg:hidden"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="-translate-x-full"
     x-transition:enter-end="translate-x-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="translate-x-0"
     x-transition:leave-end="-translate-x-full">

    <div class="p-6 border-b border-line flex items-center justify-between">
        <img src="{{ asset('images/logo-compact.png') }}" alt="RepetitiveDocs" class="h-8 w-auto object-contain">
        <button @click="mobileNavOpen = false" class="p-1 rounded-lg hover:bg-blue-soft transition-colors">
            <x-icon name="x" class="w-5 h-5 text-slate" />
        </button>
    </div>

    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        @foreach ($navItems as $item)
            @if (Route::has($item['route']))
                <a href="{{ route($item['route']) }}" @click="mobileNavOpen = false"
                   class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 text-sm font-medium
                          {{ request()->routeIs($item['route']) ? 'bg-primary text-white' : 'text-slate hover:bg-blue-soft hover:text-navy' }}">
                    <x-icon name="{{ $item['icon'] }}" class="w-5 h-5 flex-shrink-0" />
                    {{ $item['label'] }}
                </a>
            @endif
        @endforeach
    </nav>
</div>

</body>
</html>
