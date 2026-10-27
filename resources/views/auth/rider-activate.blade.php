@extends('layouts.app')

@section('title', $title . ' — Nexora Logistics')

@section('content')
<div class="min-h-[80vh] flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-surface">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="flex justify-center mb-4">
            <span class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center shadow-xs">
                <span class="material-symbols-outlined text-[28px]">two_wheeler</span>
            </span>
        </div>
        <h1 class="text-center font-headline-lg text-2xl md:text-3xl font-bold tracking-tight text-on-surface">
            {{ $title }}
        </h1>
        <p class="mt-2 text-center font-body-md text-xs md:text-sm text-on-surface-variant">
            Nexora Delivery Logistics Network
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4 sm:px-0">
        <div class="bg-surface-container-lowest py-8 px-6 sm:px-10 shadow-xs border border-outline-variant rounded-3xl">

            @if ($state === 'invalid' || $state === 'expired')
                <div class="text-center flex flex-col items-center">
                    <span class="w-14 h-14 rounded-full {{ $state === 'expired' ? 'bg-amber-500/10 text-amber-600' : 'bg-rose-500/10 text-rose-600' }} flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-[32px]">{{ $state === 'expired' ? 'schedule' : 'error' }}</span>
                    </span>

                    <h2 class="font-headline-sm text-base font-bold text-on-surface mb-2">
                        {{ $state === 'expired' ? 'Activation Link Expired' : 'Link Unavailable' }}
                    </h2>

                    <p class="font-body-sm text-xs md:text-sm text-on-surface-variant mb-6 leading-relaxed">
                        {{ $message }}
                    </p>

                    <div class="w-full flex flex-col gap-2">
                        <a href="{{ route('login') }}" class="w-full py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold text-center hover:bg-primary/90 transition-all shadow-xs">
                            Go to Sign In
                        </a>
                    </div>
                </div>

            @else
                <div class="mb-6 pb-4 border-b border-outline-variant">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/10 text-primary font-bold text-sm flex items-center justify-center">
                            {{ strtoupper(substr($rider->first_name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-label-lg text-sm font-bold text-on-surface">{{ $rider->full_name }}</p>
                            <p class="text-xs text-on-surface-variant">{{ $rider->email }}</p>
                        </div>
                    </div>
                    <p class="text-xs text-on-surface-variant mt-3">
                        Set your secure account password to complete registration and activate your rider portal.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mb-5 bg-rose-500/10 border border-rose-500/20 rounded-2xl p-4">
                        <p class="font-label-md text-xs font-bold text-rose-700">Please correct the following:</p>
                        <ul class="list-disc list-inside text-xs text-rose-600 mt-1 space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    action="{{ route('rider.activate.post', ['token' => $token]) }}"
                    method="POST"
                    class="space-y-4"
                    x-data="{
                        submitting: false,
                        password: '',
                        passwordConfirm: '',
                        showPassword: false,
                        showConfirm: false,
                        get hasMin() { return this.password.length >= 8; },
                        get hasLetter() { return /[a-zA-Z]/.test(this.password); },
                        get hasMixed() { return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password); },
                        get hasNumber() { return /\d/.test(this.password); },
                        get hasSymbol() { return /[^a-zA-Z0-9]/.test(this.password); },
                        get matches() { return this.password.length > 0 && this.password === this.passwordConfirm; }
                    }"
                    @submit="submitting = true">
                    @csrf

                    <div>
                        <label for="password" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            New Password
                        </label>
                        <div class="relative">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                id="password"
                                name="password"
                                required
                                x-model="password"
                                class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary pr-10"
                                placeholder="Min. 8 characters with mixed case, number & symbol">
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-2.5 text-on-surface-variant hover:text-on-surface">
                                <span class="material-symbols-outlined text-[18px]" x-text="showPassword ? 'visibility_off' : 'visibility'"></span>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block font-label-md text-xs font-semibold text-on-surface mb-1">
                            Confirm Password
                        </label>
                        <div class="relative">
                            <input
                                :type="showConfirm ? 'text' : 'password'"
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                x-model="passwordConfirm"
                                class="w-full rounded-xl border border-outline-variant bg-surface px-3.5 py-2.5 text-xs text-on-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary pr-10"
                                placeholder="Re-enter your password">
                            <button
                                type="button"
                                @click="showConfirm = !showConfirm"
                                class="absolute right-3 top-2.5 text-on-surface-variant hover:text-on-surface">
                                <span class="material-symbols-outlined text-[18px]" x-text="showConfirm ? 'visibility_off' : 'visibility'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Live Strength Checklist --}}
                    <div class="p-3 bg-surface-container-low rounded-xl border border-outline-variant/60 text-xs space-y-1.5">
                        <p class="font-semibold text-on-surface text-[11px] mb-1">Password Requirements:</p>
                        <div class="flex items-center gap-2" :class="hasMin ? 'text-emerald-700 font-medium' : 'text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[14px]" x-text="hasMin ? 'check_circle' : 'radio_button_unchecked'"></span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="flex items-center gap-2" :class="hasMixed ? 'text-emerald-700 font-medium' : 'text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[14px]" x-text="hasMixed ? 'check_circle' : 'radio_button_unchecked'"></span>
                            <span>Uppercase and lowercase letters</span>
                        </div>
                        <div class="flex items-center gap-2" :class="hasNumber ? 'text-emerald-700 font-medium' : 'text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[14px]" x-text="hasNumber ? 'check_circle' : 'radio_button_unchecked'"></span>
                            <span>At least one number</span>
                        </div>
                        <div class="flex items-center gap-2" :class="hasSymbol ? 'text-emerald-700 font-medium' : 'text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[14px]" x-text="hasSymbol ? 'check_circle' : 'radio_button_unchecked'"></span>
                            <span>At least one special character symbol</span>
                        </div>
                        <div class="flex items-center gap-2" :class="matches ? 'text-emerald-700 font-medium' : 'text-on-surface-variant'">
                            <span class="material-symbols-outlined text-[14px]" x-text="matches ? 'check_circle' : 'radio_button_unchecked'"></span>
                            <span>Passwords match</span>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="submitting || !hasMin || !hasMixed || !hasNumber || !hasSymbol || !matches"
                        class="w-full mt-4 py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                        <span x-show="submitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                        <span x-text="submitting ? 'Activating Account…' : 'Activate Account & Sign In'"></span>
                    </button>
                </form>
            @endif

        </div>
    </div>
</div>
@endsection
