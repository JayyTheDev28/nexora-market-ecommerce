@extends('layouts.app')

@section('title', 'Forgot Password — Nexora Market')

@section('content')
<div class="w-full min-h-[calc(100vh-4rem)] py-12 px-4 sm:px-6 flex items-center justify-center bg-surface-container-low">
    <div class="max-w-md w-full my-auto flex flex-col gap-6">
        <div class="text-center">
            <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mb-4 group focus:outline-none">
                <img src="{{ asset('images/nexora-logo.png') }}" alt="Nexora Logo" class="w-10 h-10 rounded-xl object-cover shadow-xs">
                <span class="font-headline-md text-xl font-extrabold tracking-tight text-on-surface">Nexora <span class="text-xs px-1.5 py-0.5 rounded bg-primary text-on-primary font-bold uppercase tracking-wider">Market</span></span>
            </a>
            <h1 class="font-headline-lg text-2xl font-bold tracking-tight text-on-surface">Forgot your password?</h1>
            <p class="font-body-md text-xs text-on-surface-variant mt-1.5">
                Enter your registered email address or mobile number to receive a 6-digit password reset code.
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

        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant p-6 sm:p-8 shadow-xs">
            <form class="flex flex-col gap-4" action="{{ route('password.email') }}" method="POST">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label for="email" class="font-label-md text-xs font-semibold text-on-surface-variant">
                        Email Address or Mobile Number
                    </label>
                    <input
                        type="text"
                        id="email"
                        name="email"
                        value="{{ $prefillEmail ?? '' }}"
                        required
                        autofocus
                        placeholder="name@example.com or 09XXXXXXXXX"
                        class="w-full rounded-xl border border-outline-variant px-3.5 py-2.5 text-xs text-on-surface bg-surface placeholder:text-outline focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
                </div>

                <button
                    type="submit"
                    class="w-full flex items-center justify-center py-2.5 px-4 rounded-full bg-primary text-on-primary font-label-md text-xs font-semibold shadow-xs hover:bg-primary/90 transition-all cursor-pointer">
                    Send Reset Code
                </button>
            </form>
        </div>

        <div class="text-center text-xs text-on-surface-variant">
            <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">← Back to Sign In</a>
        </div>
    </div>
</div>
@endsection
