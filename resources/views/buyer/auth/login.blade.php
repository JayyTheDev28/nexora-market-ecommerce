@extends('layouts.app')

@section('title', 'Sign In — Nexora Market')

@section('content')
<div
    x-data="loginForm()"
    class="w-full min-h-[calc(100vh-4rem)] py-12 px-4 sm:px-6 flex items-center justify-center bg-surface-container-low">

    <div class="max-w-md w-full my-auto flex flex-col gap-6">

        {{-- Centered Header & Branding --}}
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-4 group focus:outline-none">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-10 h-10 rounded-xl object-cover shadow-xs group-hover:scale-105 transition-transform">
                <span class="font-headline-md text-xl font-extrabold tracking-tight text-on-surface">Nexora <span class="text-xs px-1.5 py-0.5 rounded bg-primary text-on-primary font-bold uppercase tracking-wider">Market</span></span>
            </a>
            <h1 class="font-headline-lg text-2xl font-bold tracking-tight text-on-surface">
                Sign in to your account
            </h1>
            <p class="font-body-md text-xs text-on-surface-variant mt-1.5">
                Welcome back! Please enter your details below.
            </p>
        </div>

        {{-- Status Alerts --}}
        @if ($errors->any())
            <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-body-sm text-xs font-semibold text-rose-700 leading-snug">{{ $errors->first() }}</p>
            </div>
        @endif

        @if (session('verified'))
            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4">
                <p class="font-body-sm text-xs font-semibold text-emerald-700 leading-snug">Email verified successfully! Please sign in to continue.</p>
            </div>
        @endif

        {{-- Login Card & Form --}}
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-8 shadow-xs">
            <form
                class="flex flex-col gap-4"
                action="{{ route('login.store') }}"
                method="POST"
                @submit="isSubmitting = true">
                @csrf

                {{-- Email Address --}}
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">
                        Email Address
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        placeholder="name@example.com"
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                {{-- Password --}}
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between">
                        <label for="password" class="font-label-md text-xs font-semibold text-on-surface-variant">
                            Password
                        </label>
                        <a href="#" class="font-label-md text-[11px] text-primary hover:underline font-semibold">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            placeholder="Enter your password"
                            class="w-full rounded-xl border border-outline-variant pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer transition-colors"
                            aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center justify-between py-1">
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            name="remember"
                            class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary cursor-pointer">
                        <span class="text-xs text-on-surface-variant font-medium">Remember me</span>
                    </label>
                </div>

                {{-- Submit Button --}}
                <button
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full flex items-center justify-center py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all mt-1 cursor-pointer disabled:opacity-50">
                    <span x-text="isSubmitting ? 'Signing in…' : 'Sign In'"></span>
                </button>
            </form>
        </div>

        {{-- Footer Links --}}
        <div class="text-center text-xs text-on-surface-variant space-y-2">
            <p>
                Don't have an account?
                <a href="{{ url('/register') }}" class="text-primary font-semibold hover:underline">Sign up</a>
                <span class="mx-1 text-outline-variant">•</span>
                <a href="{{ route('logistics.register') }}" class="text-primary font-semibold hover:underline">Logistics Partner</a>
            </p>
            <div class="flex items-center justify-center gap-3 text-[11px] text-outline pt-1">
                <span>&copy; {{ now()->year }} Nexora Market</span>
                <span>•</span>
                <a href="{{ route('privacy') }}" class="hover:text-on-surface-variant transition-colors">Privacy</a>
                <span>•</span>
                <a href="{{ route('terms') }}" class="hover:text-on-surface-variant transition-colors">Terms</a>
            </div>
        </div>

    </div>

</div>

@push('scripts')
<script>
    function loginForm() {
        return {
            showPassword: false,
            isSubmitting: false,
        };
    }
</script>
@endpush
@endsection
