<x-layouts.auth title="Sign In — RepetitiveDocs">
<div class="min-h-screen bg-gradient-to-b from-white to-canvas flex items-center justify-center p-6">
    <div class="w-full max-w-5xl grid lg:grid-cols-2 gap-12 items-center">

        {{-- Left — Loopi welcome panel --}}
        <div class="hidden lg:block">
            <div class="bg-gradient-to-br from-blue-light to-white rounded-3xl p-12 shadow-xl border border-line">
                <div class="flex flex-col items-center text-center">
                    <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-48 h-48 object-contain mb-6">
                    <h2 class="text-2xl font-bold text-navy mb-3">Welcome back!</h2>
                    <p class="text-slate mb-6">Continue automating your documents with Loopi</p>
                    <div class="space-y-3 w-full text-left">
                        @foreach (['Access your templates', 'Generate documents', 'View your history'] as $feature)
                        <div class="flex items-center gap-3 bg-white rounded-xl p-3">
                            <x-icon name="sparkles" class="w-5 h-5 text-primary flex-shrink-0" />
                            <span class="text-sm text-navy">{{ $feature }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Right — Login form --}}
        <div class="bg-white rounded-3xl p-8 shadow-xl border border-line">
            <div class="mb-8">
                <img src="{{ asset('images/logo-friendly.png') }}" alt="RepetitiveDocs" class="h-10 w-auto object-contain">
            </div>

            <h1 class="text-2xl font-bold text-navy mb-1">Sign in to your account</h1>
            <p class="text-slate mb-6 text-sm">Welcome back! Please enter your details</p>

            {{-- Session / validation errors --}}
            @if (session('error'))
                <div class="mb-4 flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
                    <x-icon name="alert-circle" class="w-4 h-4 flex-shrink-0" />
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
                    <x-icon name="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Login form --}}
            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-navy mb-1.5">Email Address</label>
                    <div class="relative">
                        <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                               placeholder="you@example.com"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('email') border-danger @enderror">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-medium text-navy">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-primary hover:text-primary-dark transition-colors">Forgot password?</a>
                    </div>
                    <div class="relative" x-data="{ show: false }">
                        <x-icon name="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-10 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('password') border-danger @enderror">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate hover:text-navy transition-colors">
                            <x-icon :name="'eye'" x-show="!show" class="w-4 h-4" />
                            <x-icon :name="'eye-off'" x-show="show" class="w-4 h-4" />
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input id="remember" name="remember" type="checkbox" class="w-4 h-4 rounded border-line text-primary focus:ring-primary/20">
                    <label for="remember" class="text-sm text-slate">Remember me</label>
                </div>

                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors duration-200"
                        data-loading-text="Signing in…">
                    Sign In
                </button>
            </form>

            <p class="text-sm text-center text-slate mt-6">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-primary hover:text-primary-dark font-medium transition-colors">Sign up for free</a>
            </p>
        </div>

    </div>
</div>
</x-layouts.auth>
