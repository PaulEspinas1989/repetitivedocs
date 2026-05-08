<x-layouts.auth title="Reset Password — RepetitiveDocs">
<div class="min-h-screen bg-gradient-to-br from-blue-soft to-blue-light flex items-center justify-center p-6">
    <div class="w-full max-w-md">
        <a href="{{ route('login') }}" class="inline-block mb-8">
            <img src="{{ asset('images/logo-primary.png') }}" alt="RepetitiveDocs" class="h-9 w-auto object-contain">
        </a>

        <div class="bg-white rounded-2xl p-8 shadow-xl border border-line">
            <h1 class="text-2xl font-bold text-navy mb-2">Set a new password</h1>
            <p class="text-slate text-sm mb-6">Choose a strong password for your account.</p>

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

            <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-sm font-medium text-navy mb-1.5">Email Address</label>
                    <div class="relative">
                        <x-icon name="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                        <input id="email" name="email" type="email" value="{{ old('email', request('email')) }}" required
                               placeholder="you@example.com"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors @error('email') border-danger @enderror">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-navy mb-1.5">New Password</label>
                    <div class="relative" x-data="{ show: false }">
                        <x-icon name="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
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
                    <label for="password_confirmation" class="block text-sm font-medium text-navy mb-1.5">Confirm New Password</label>
                    <div class="relative">
                        <x-icon name="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-muted pointer-events-none" />
                        <input id="password_confirmation" name="password_confirmation" type="password" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-3 border border-line rounded-xl bg-white text-navy text-sm placeholder-muted focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-colors">
                    </div>
                </div>

                <button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary text-white py-3 rounded-xl font-semibold text-sm hover:bg-primary-dark transition-colors"
                        data-loading-text="Updating your password…">
                    Reset Password
                </button>
            </form>
        </div>
    </div>
</div>
</x-layouts.auth>
