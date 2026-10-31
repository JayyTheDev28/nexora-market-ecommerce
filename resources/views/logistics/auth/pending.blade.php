@extends('layouts.app')

@section('title', 'Application Pending Compliance Review — Nexora Market')

@section('content')
<div class="w-full bg-surface-container-low py-12 md:py-20 min-h-[calc(100vh-4rem)] flex items-center justify-center">
    <div class="max-w-lg w-full px-4">
        <div class="bg-surface-container-lowest rounded-3xl border border-outline-variant shadow-xs p-7 sm:p-10 text-center flex flex-col items-center gap-6">

            {{-- Icon --}}
            <div class="w-16 h-16 rounded-3xl bg-amber-500/10 text-amber-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[36px]">pending_actions</span>
            </div>

            <div>
                <span class="inline-block px-3 py-1 rounded-full bg-amber-500/10 text-amber-700 text-xs font-bold mb-2">
                    Pending Compliance Review
                </span>
                <h1 class="font-headline-lg text-xl sm:text-2xl font-bold tracking-tight text-on-surface">
                    Application Under Review
                </h1>
                <p class="text-xs text-on-surface-variant mt-2 leading-relaxed">
                    Thank you for completing your email verification. Your logistics partnership application for
                    <strong class="text-on-surface">{{ $profile->company_name ?? 'your company' }}</strong>
                    is currently being reviewed by our compliance administrators.
                </p>
            </div>

            {{-- Checklist --}}
            <div class="w-full bg-surface-container-low rounded-2xl p-4 border border-outline-variant/60 text-left flex flex-col gap-2.5 text-xs">
                <div class="flex items-center gap-2.5 text-emerald-700 font-semibold">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    <span>Corporate Registration Details Submitted</span>
                </div>
                <div class="flex items-center gap-2.5 text-emerald-700 font-semibold">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    <span>Corporate Email OTP Verified</span>
                </div>
                <div class="flex items-center gap-2.5 text-amber-700 font-semibold">
                    <span class="material-symbols-outlined text-[18px]">hourglass_top</span>
                    <span>Administrator Document Audit (SEC, BIR 2303, Mayor's Permit, ID)</span>
                </div>
            </div>

            <p class="text-[11px] text-on-surface-variant leading-relaxed">
                Applications are typically reviewed within 24–48 business hours. You will receive an official decision notification via email as soon as the review is complete.
            </p>

            {{-- Sign Out / Back --}}
            <div class="w-full pt-2 border-t border-outline-variant/60 flex items-center justify-center gap-4 text-xs">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-5 py-2.5 rounded-full border border-outline-variant text-on-surface font-semibold hover:bg-surface-container transition-colors cursor-pointer">
                        Sign Out
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>
@endsection

