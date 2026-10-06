@extends('layouts.app')

@section('title', 'Verify Your Email — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-16 md:py-24">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">
        <div class="max-w-xl mx-auto bg-surface-container-lowest rounded-3xl shadow-xl p-8 md:p-12 text-center flex flex-col items-center gap-5">

            <div class="w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center">
                <span class="material-symbols-outlined text-[30px] text-primary">mark_email_unread</span>
            </div>

            <h1 class="font-headline-lg text-headline-lg text-on-surface">Verify Your Email</h1>

            <p class="font-body-md text-body-md text-on-surface-variant">
                We sent a verification link to <span class="font-semibold text-on-surface">{{ auth()->user()->email }}</span>.
                Click the link in that email to confirm your address.
            </p>

            @if (session('status') === 'verification-link-sent')
                <div class="w-full bg-primary/10 border border-primary/30 rounded-xl p-3">
                    <p class="font-body-sm text-body-sm text-primary">A new verification link has been sent.</p>
                </div>
            @endif

            <p class="font-body-sm text-body-sm text-on-surface-variant">
                Didn't get the email? Check your spam folder, or request a new one below.
            </p>

            <form method="POST" action="{{ route('verification.send') }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 bg-primary text-on-primary font-label-md text-label-md px-6 py-3 rounded-full hover:bg-primary/90 transition-all">
                    Resend Verification Email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="font-label-md text-label-md text-on-surface-variant hover:text-on-surface hover:underline">
                    Log Out
                </button>
            </form>

        </div>
    </div>
</div>
@endsection
