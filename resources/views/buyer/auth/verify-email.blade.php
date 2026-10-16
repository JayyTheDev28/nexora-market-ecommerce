@extends('layouts.app')

@section('title', 'Verify Your Email — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-14 md:py-20 min-h-[75vh] flex items-center justify-center">
    <div class="max-w-none px-4 md:px-8 w-full">
        <div class="max-w-md mx-auto bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs p-8 md:p-10 text-center flex flex-col items-center gap-5">

            <div class="w-14 h-14 rounded-2xl bg-primary/10 text-primary flex items-center justify-center">
                <span class="material-symbols-outlined text-[28px]">mark_email_unread</span>
            </div>

            <div>
                <h1 class="font-headline-lg text-xl md:text-2xl font-bold tracking-tight text-on-surface">Verify Your Email</h1>
                <p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed">
                    We sent a verification link to <span class="font-semibold text-on-surface">{{ auth()->user()->email }}</span>. Please click the link in that email to confirm your account.
                </p>
            </div>

            @if (session('status') === 'verification-link-sent')
                <div class="w-full bg-emerald-500/10 border border-emerald-500/20 rounded-2xl p-3 text-left">
                    <p class="text-xs font-semibold text-emerald-700">A new verification link has been sent to your email address.</p>
                </div>
            @endif

            <p class="text-[11px] text-on-surface-variant">
                Didn't receive the email? Check your spam folder or request a new verification link below.
            </p>

            <div class="flex flex-col gap-2.5 w-full pt-1">
                <form method="POST" action="{{ route('verification.send') }}" class="w-full">
                    @csrf
                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-5 py-2.5 rounded-full shadow-xs hover:bg-primary/90 transition-colors cursor-pointer">
                        Resend Verification Email
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full py-2 text-xs font-semibold text-on-surface-variant hover:text-error transition-colors cursor-pointer">
                        Sign Out
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection
