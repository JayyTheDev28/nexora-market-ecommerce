@extends('layouts.app')

@section('title', 'Reset Password — Nexora Market')

@section('content')
<div x-data="{ showPw: false }" class="w-full min-h-[calc(100vh-4rem)] py-12 px-4 sm:px-6 flex items-center justify-center bg-surface-container-low">
    <div class="max-w-md w-full my-auto flex flex-col gap-6">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-4 group focus:outline-none">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-10 h-10 rounded-xl object-cover shadow-xs">
                <span class="font-headline-md text-xl font-extrabold tracking-tight text-on-surface">Nexora <span class="text-xs px-1.5 py-0.5 rounded bg-primary text-on-primary font-bold uppercase tracking-wider">Market</span></span>
            </a>
            <h1 class="font-headline-lg text-2xl font-bold tracking-tight text-on-surface">Reset your password</h1>
            <p class="font-body-md text-xs text-on-surface-variant mt-1.5">
                Enter the 6-digit code sent to your email and choose a strong new password.
            </p>
        </div>

        @if ($errors->any())
            <div class="bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                <p class="font-body-sm text-xs font-semibold text-rose-700 leading-snug">{{ $errors->first() }}</p>
            </div>
        @endif

        @if (session('status'))
            <div class="bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-4">
                <p class="font-body-sm text-xs font-semibold text-emerald-700 leading-snug">{{ session('status') }}</p>
            </div>
        @endif

        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-8 shadow-xs space-y-4">
            <form class="flex flex-col gap-4" action="{{ route('password.update') }}" method="POST">
                @csrf

                <div class="flex flex-col gap-1.5">
                    <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">Account Email</label>
                    <input
                        type="text"
                        id="email"
                        name="email"
                        value="{{ $email }}"
                        required
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="otp" class="font-label-md text-xs font-semibold text-on-surface-variant">6-Digit Reset Code</label>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        value="{{ old('otp') }}"
                        maxlength="6"
                        inputmode="numeric"
                        required
                        placeholder="000000"
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-sm font-mono tracking-widest text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password" class="font-label-md text-xs font-semibold text-on-surface-variant">New Password (min 8 chars)</label>
                    <div class="relative">
                        <input
                            :type="showPw ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            minlength="8"
                            placeholder="Enter new password"
                            class="w-full rounded-xl border border-outline-variant pl-3.5 pr-11 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="button" @click="showPw = !showPw" class="absolute right-3 top-1/2 -translate-y-1/2 text-outline hover:text-on-surface-variant p-1 cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]" x-text="showPw ? 'visibility_off' : 'visibility'"></span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="password_confirmation" class="font-label-md text-xs font-semibold text-on-surface-variant">Confirm New Password</label>
                    <input
                        :type="showPw ? 'text' : 'password'"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        minlength="8"
                        placeholder="Re-enter new password"
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface focus:outline-none focus:ring-2 focus:ring-primary">
                </div>

                <button type="submit" class="w-full py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                    Update Password
                </button>
            </form>

            @if(!empty($email))
                <form method="POST" action="{{ route('password.email') }}" class="pt-2 border-t border-outline-variant/50 text-center">
                    @csrf
                    <input type="hidden" name="email" value="{{ $email }}">
                    <button type="submit" class="text-xs font-semibold text-primary hover:underline cursor-pointer">Resend 6-digit code</button>
                </form>
            @endif
        </div>

        <div class="text-center text-xs text-on-surface-variant">
            <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">← Back to Sign In</a>
        </div>
    </div>
</div>
@endsection
