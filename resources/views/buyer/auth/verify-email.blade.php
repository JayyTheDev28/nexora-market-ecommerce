@extends('layouts.app')

@section('title', 'Verify Your Email — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-12 md:py-20 min-h-[calc(100vh-4rem)] flex items-center justify-center">
    <div class="max-w-md w-full px-4">
        <div
            x-data="otpForm()"
            class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs p-7 sm:p-9 text-center flex flex-col items-center gap-6">

            {{-- Icon / Header --}}
            <div class="w-12 h-12 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[26px]">mark_email_read</span>
            </div>

            <div>
                <h1 class="font-headline-lg text-xl sm:text-2xl font-bold tracking-tight text-on-surface">
                    Enter Verification Code
                </h1>
                <p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed">
                    We sent a 6-digit verification code to <br>
                    <span class="font-semibold text-on-surface">{{ auth()->user()->email }}</span>
                </p>
            </div>

            {{-- Status Alert (e.g. Resent Code) --}}
            @if (session('status') === 'otp-sent' || session('status') === 'verification-link-sent')
                <div class="w-full bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-3 text-center">
                    <p class="text-xs font-semibold text-emerald-700">A new 6-digit verification code has been sent to your email.</p>
                </div>
            @endif

            {{-- Error Alert --}}
            @if ($errors->has('otp'))
                <div class="w-full bg-rose-500/10 border border-rose-500/20 rounded-2xl p-3 text-center">
                    <p class="text-xs font-semibold text-rose-700">{{ $errors->first('otp') }}</p>
                </div>
            @endif

            {{-- OTP Form --}}
            <form
                method="POST"
                action="{{ route('verification.verify') }}"
                class="w-full flex flex-col items-center gap-5"
                @submit="handleSubmit($event)">
                @csrf

                {{-- Hidden input for full OTP --}}
                <input type="hidden" name="otp" :value="otpValue">

                {{-- 6 PIN Input Boxes --}}
                <div class="flex items-center justify-center gap-2 sm:gap-2.5 w-full select-none" @paste="handlePaste($event)">
                    <template x-for="(digit, idx) in digits" :key="idx">
                        <input
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="1"
                            autocomplete="one-time-code"
                            :x-ref="'digit' + idx"
                            :value="digit"
                            @input="handleInput(idx, $event)"
                            @keydown="handleKeydown(idx, $event)"
                            @focus="$event.target.select()"
                            class="w-11 h-13 sm:w-12 sm:h-14 text-center text-xl sm:text-2xl font-extrabold rounded-2xl border bg-surface text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all"
                            :class="fieldError ? 'border-rose-500' : 'border-outline-variant'">
                    </template>
                </div>

                <p class="text-[11px] text-on-surface-variant">
                    Code expires in 15 minutes.
                </p>

                {{-- Submit Button --}}
                <button
                    type="submit"
                    :disabled="otpValue.length !== 6 || isSubmitting"
                    class="w-full inline-flex items-center justify-center py-2.5 px-5 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!isSubmitting">Verify Code</span>
                    <span x-show="isSubmitting" class="flex items-center gap-2">
                        <span class="w-3.5 h-3.5 border-2 border-on-primary border-t-transparent rounded-full animate-spin"></span>
                        <span>Verifying…</span>
                    </span>
                </button>
            </form>

            {{-- Resend & Signout Footer --}}
            <div class="flex flex-col items-center gap-3 w-full pt-1 border-t border-outline-variant/60 text-xs">
                <div class="flex items-center gap-1.5 text-on-surface-variant">
                    <span>Didn't receive the code?</span>
                    <form method="POST" action="{{ route('verification.send') }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            :disabled="cooldown > 0"
                            @click="startCooldown(60)"
                            class="text-primary font-semibold hover:underline cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed">
                            <span x-show="cooldown <= 0">Resend Code</span>
                            <span x-show="cooldown > 0" x-text="'Resend in ' + cooldown + 's'"></span>
                        </button>
                    </form>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs text-on-surface-variant hover:text-error transition-colors cursor-pointer">
                        Sign Out
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

@push('scripts')
<script>
    function otpForm() {
        return {
            digits: ['', '', '', '', '', ''],
            isSubmitting: false,
            fieldError: {{ $errors->has('otp') ? 'true' : 'false' }},
            cooldown: {{ session('status') === 'otp-sent' ? '60' : '0' }},
            timer: null,

            get otpValue() {
                return this.digits.join('');
            },

            init() {
                if (this.cooldown > 0) {
                    this.startCooldown(this.cooldown);
                }
                this.$nextTick(() => {
                    this.$refs.digit0?.focus();
                });
            },

            handleInput(index, event) {
                this.fieldError = false;
                const val = event.target.value.replace(/[^0-9]/g, '');
                this.digits[index] = val ? val.slice(-1) : '';

                if (this.digits[index] && index < 5) {
                    this.$refs['digit' + (index + 1)]?.focus();
                }
            },

            handleKeydown(index, event) {
                if (event.key === 'Backspace') {
                    if (!this.digits[index] && index > 0) {
                        this.digits[index - 1] = '';
                        this.$refs['digit' + (index - 1)]?.focus();
                    } else {
                        this.digits[index] = '';
                    }
                } else if (event.key === 'ArrowLeft' && index > 0) {
                    this.$refs['digit' + (index - 1)]?.focus();
                } else if (event.key === 'ArrowRight' && index < 5) {
                    this.$refs['digit' + (index + 1)]?.focus();
                }
            },

            handlePaste(event) {
                event.preventDefault();
                const text = (event.clipboardData || window.clipboardData).getData('text');
                const clean = text.replace(/[^0-9]/g, '').slice(0, 6);
                if (clean) {
                    for (let i = 0; i < 6; i++) {
                        this.digits[i] = clean[i] || '';
                    }
                    const focusIdx = Math.min(clean.length, 5);
                    this.$refs['digit' + focusIdx]?.focus();
                }
            },

            handleSubmit(event) {
                if (this.otpValue.length !== 6) {
                    event.preventDefault();
                    return;
                }
                this.isSubmitting = true;
            },

            startCooldown(seconds = 60) {
                this.cooldown = seconds;
                if (this.timer) clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (this.cooldown > 0) {
                        this.cooldown--;
                    } else {
                        clearInterval(this.timer);
                    }
                }, 1000);
            }
        };
    }
</script>
@endpush
@endsection
