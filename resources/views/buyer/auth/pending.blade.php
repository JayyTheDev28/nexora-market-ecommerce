@extends('layouts.app')

@section('title', 'Application Submitted — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-14 md:py-20 min-h-[75vh] flex items-center justify-center">
    <div class="max-w-xl mx-auto px-4 sm:px-6 w-full">
        <div class="max-w-md mx-auto bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs p-8 md:p-10 text-center flex flex-col items-center gap-5">

            <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[28px]">check_circle</span>
            </div>

            <div>
                <h1 class="font-headline-lg text-xl md:text-2xl font-bold tracking-tight text-on-surface">Application Under Review</h1>
                <p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed">
                    Thank you, <span class="font-semibold text-on-surface">{{ auth()->user()->first_name }}</span>. Your registration details have been submitted and are pending administrative verification.
                </p>
            </div>

            <div class="w-full p-3.5 bg-surface-container rounded-2xl text-xs text-on-surface-variant leading-relaxed text-left">
                <p class="font-semibold text-on-surface mb-1">What happens next?</p>
                <p>A marketplace administrator will review your submitted credentials. You will receive an email notification at <span class="font-medium text-on-surface">{{ auth()->user()->email }}</span> once your account has been approved.</p>
            </div>

            <div class="flex flex-col gap-2.5 w-full pt-2">
                <a
                    href="{{ auth()->check() ? url(auth()->user()->homeRoute()) : url('/') }}"
                    class="w-full inline-flex items-center justify-center bg-primary text-on-primary font-label-md text-xs font-semibold px-5 py-2.5 rounded-full shadow-xs hover:bg-primary/90 transition-colors">
                    Back to Marketplace
                </a>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <button type="submit" class="w-full py-2 text-xs font-semibold text-on-surface-variant hover:text-error transition-colors">
                        Sign Out
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection
