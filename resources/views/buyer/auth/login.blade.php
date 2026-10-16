@extends('layouts.app')

@section('title', 'Sign In — Nexora Market')

@section('content')
<div
    x-data="loginForm()"
    class="w-full flex flex-col md:flex-row min-h-[calc(100vh-4rem)] bg-surface-container-lowest">

    <x-brand-panel caption="Sign in to track orders, manage your wishlist, and check out faster." />

    {{-- Form Column --}}
    <div class="flex-1 overflow-y-auto flex items-center justify-center py-10 px-6 md:px-12">
        <div class="max-w-md w-full">

            <div class="mb-6">
                <h1 class="font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">Welcome Back</h1>
                <p class="font-body-md text-xs md:text-sm text-on-surface-variant mt-1.5">
                    Sign in to continue to your Nexora Market account.
                </p>
            </div>

            @if ($errors->any())
                <div class="mb-6 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                    <p class="font-body-sm text-xs font-semibold text-rose-700">{{ $errors->first() }}</p>
                </div>
            @endif

            @if (session('verified'))
                <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4">
                    <p class="font-body-sm text-xs font-semibold text-emerald-700">Email verified successfully! Please sign in to continue.</p>
                </div>
            @endif

            <form
                class="flex flex-col gap-4 bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs p-6 md:p-8"
                action="{{ route('login.store') }}" method="POST">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">Email Address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        placeholder="name@example.com"
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between">
                        <label for="password" class="font-label-md text-xs font-semibold text-on-surface-variant">Password</label>
                        <a href="#" class="font-label-md text-[11px] text-primary hover:underline">Forgot password?</a>
                    </div>
                    <div class="relative">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            placeholder="••••••••"
                            class="w-full rounded-xl border border-outline-variant pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary">
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer"
                            aria-label="Toggle password visibility">
                            <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between py-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="w-4 h-4 rounded border-outline-variant text-primary focus:ring-primary">
                        <span class="text-xs text-on-surface-variant">Remember me</span>
                    </label>
                </div>

                <button
                    type="submit"
                    class="w-full flex items-center justify-center py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all mt-1 cursor-pointer">
                    Sign In
                </button>

                <p class="text-center text-xs text-on-surface-variant mt-2">
                    Don't have an account?
                    <a href="{{ url('/register') }}" class="text-primary font-semibold hover:underline">Create an account</a>
                </p>

            </form>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function loginForm() {
        return {
            showPassword: false,
        };
    }
</script>
@endpush
@endsection
