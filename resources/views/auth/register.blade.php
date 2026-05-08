<x-layouts.auth title="Create Account — RepetitiveDocs">
<div class="min-h-screen bg-gradient-to-b from-white to-canvas flex items-center justify-center p-6">
    <div class="w-full max-w-5xl grid lg:grid-cols-2 gap-12 items-center">

        {{-- Left — Loopi welcome panel --}}
        <div class="hidden lg:block">
            <div class="bg-gradient-to-br from-blue-light to-white rounded-3xl p-12 shadow-xl border border-line">
                <div class="flex flex-col items-center text-center">
                    <img src="{{ asset('images/loopi-welcome.png') }}" alt="Loopi" class="w-48 h-48 object-contain mb-6">
                    <h2 class="text-2xl font-bold text-navy mb-3">Meet Loopi, your AI document assistant</h2>
                    <p class="text-slate mb-6 text-sm">Upload your first document in less than 3 minutes</p>
                    <div class="space-y-3 w-full text-left">
                        @foreach (['AI finds what changes', 'Creates reusable templates', 'Generates personalized copies'] as $feature)
                        <div class="flex items-center gap-3 bg-white rounded-xl p-3">
                            <x-icon name="sparkles" class="w-5 h-5 text-primary flex-shrink-0" />
                            <span class="text-sm text-navy">{{ $feature }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Right — Register form --}}
        <div class="bg-white rounded-3xl p-8 shadow-xl border border-line">
            <div class="mb-8">
                <img src="{{ asset('images/logo-friendly.png') }}" alt="RepetitiveDocs" class="h-10 w-auto object-contain">
            </div>

            <h1 class="text-2xl font-bold text-navy mb-1">Create your account</h1>
            <p class="text-slate mb-6 text-sm">Start automating your documents today</p>

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

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-navy mb-1.5">Full Name</label>
                    <div class="relative">
                        <x-icon name="user" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus
                               placeholder="Juan dela Cruz"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('name') border-danger @enderror">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-navy mb-1.5">Email Address</label>
                    <div class="relative">
                        <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required
                               placeholder="you@example.com"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('email') border-danger @enderror">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-navy mb-1.5">Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <x-icon name="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-10 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('password') border-danger @enderror">
                        <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate hover:text-navy transition-colors">
                            <x-icon name="eye" x-show="!show" class="w-4 h-4" />
                            <x-icon name="eye-off" x-show="show" class="w-4 h-4" />
                        </button>
                    </div>
                    <p class="text-xs text-muted mt-1">Must be at least 8 characters</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-navy mb-1.5">Confirm Password</label>
                    <div class="relative">
                        <x-icon name="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate pointer-events-none" />
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    </div>
                </div>

                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors duration-200"
                        data-loading-text="Creating your account…">
                    Create Account
                </button>
            </form>

            <p class="text-sm text-center text-slate mt-6">
                Already have an account?
                <a href="{{ route('login') }}" class="text-primary hover:text-primary-dark font-medium transition-colors">Sign in</a>
            </p>

            <p class="text-xs text-center text-muted mt-3">
                By signing up, you agree to our Terms of Service and Privacy Policy
            </p>
        </div>

    </div>
</div>
</x-layouts.auth>
