<x-layouts.auth title="Forgot Password — RepetitiveDocs">
<div class="min-h-screen bg-gradient-to-br from-blue-soft to-blue-light flex">

    {{-- Left — Form --}}
    <div class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <a href="{{ route('login') }}" class="inline-block mb-8">
                <img src="{{ asset('images/logo-primary.png') }}" alt="RepetitiveDocs" class="h-9 w-auto object-contain">
            </a>

            @if (session('status'))
                {{-- Success state --}}
                <div class="bg-white rounded-2xl p-8 shadow-xl border border-line text-center">
                    <div class="w-16 h-16 rounded-3xl bg-success/10 flex items-center justify-center mx-auto mb-5">
                        <x-icon name="check-circle" class="w-8 h-8 text-success" />
                    </div>
                    <h1 class="text-2xl font-bold text-navy mb-2">Check your inbox</h1>
                    <p class="text-slate text-sm mb-6 leading-relaxed">{{ session('status') }}</p>
                    <a href="{{ route('login') }}" class="inline-flex items-center gap-2 w-full justify-center bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors">
                        <x-icon name="arrow-left" class="w-4 h-4" />
                        Back to Login
                    </a>
                </div>
            @else
                <div class="bg-white rounded-2xl p-8 shadow-xl border border-line">
                    <h1 class="text-2xl font-bold text-navy mb-2">Forgot your password?</h1>
                    <p class="text-slate text-sm mb-6">Enter your email and we'll send you a reset link.</p>

                    @if ($errors->any())
                        <div class="mb-4 flex items-start gap-3 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-danger">
                            <x-icon name="alert-circle" class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <ul class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="email" class="block text-sm font-medium text-navy mb-1.5">Email Address</label>
                            <div class="relative">
                                <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                                       placeholder="you@example.com"
                                       class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                            </div>
                        </div>
                        <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors"
                                data-loading-text="Sending reset link…">
                            Send Reset Link
                        </button>
                    </form>

                    <div class="mt-5 text-center">
                        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 text-sm text-primary hover:text-primary-dark transition-colors">
                            <x-icon name="arrow-left" class="w-4 h-4" />
                            Back to Login
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Right — Blue panel --}}
    <div class="hidden lg:flex flex-1 items-center justify-center p-12 bg-gradient-to-br from-primary to-primary-dark">
        <div class="max-w-md text-white">
            <h2 class="text-3xl font-bold mb-4">Account Recovery</h2>
            <p class="text-white/90 text-sm leading-relaxed mb-8">
                We'll help you regain access to your RepetitiveDocs account quickly and securely.
            </p>
            <div class="space-y-4">
                @foreach (['Reset link sent to your email', 'Link expires in 1 hour', 'Create a new secure password', 'Regain access immediately'] as $step)
                <div class="flex items-center gap-3">
                    <x-icon name="check-circle" class="w-5 h-5 flex-shrink-0" />
                    <span class="text-sm text-white/90">{{ $step }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

</div>
</x-layouts.auth>
