@props([
    'title',        // Dashboard heading
    'icon',         // Material Symbols icon name
    'accent',       // Tailwind bg class for the icon badge
    'features' => [], // Upcoming features from the ERP spec
])

@php $user = auth()->user(); @endphp

<div class="w-full bg-surface py-8 min-h-[70vh]">
    <div class="max-w-none px-margin-mobile md:px-margin-desktop">

        {{-- Temporary placeholder banner — this whole component exists so you
             can confirm role-based routing works. Each role's real interface
             replaces it in its own phase. --}}
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-tertiary/30 bg-tertiary-fixed/40 p-4">
            <span class="material-symbols-outlined text-tertiary text-[20px]">construction</span>
            <div>
                <p class="font-label-md text-label-md text-on-tertiary-fixed">Placeholder interface</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    This confirms you're logged in and routed to the correct role's area. The real interface gets built in a later phase.
                </p>
            </div>
        </div>

        <div class="flex flex-col md:flex-row md:items-center gap-4 mb-8">
            <div class="w-14 h-14 rounded-2xl {{ $accent }} flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-[26px] text-on-primary">{{ $icon }}</span>
            </div>
            <div class="flex-1">
                <h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $title }}</h1>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Welcome back, {{ $user->first_name }}.
                </p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex items-center gap-2 rounded-full border border-outline-variant px-5 py-2.5 font-label-md text-label-md text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    Log Out
                </button>
            </form>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Account details --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Your Account</h2>
                <dl class="flex flex-col gap-3 font-body-sm text-body-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Name</dt>
                        <dd class="text-on-surface text-right">{{ $user->full_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Email</dt>
                        <dd class="text-on-surface text-right break-all">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Role</dt>
                        <dd class="text-on-surface text-right">{{ $user->roleLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-on-surface-variant">Contact</dt>
                        <dd class="text-on-surface text-right">{{ $user->contact_number }}</dd>
                    </div>
                    @if($user->address)
                        <div class="flex justify-between gap-4">
                            <dt class="text-on-surface-variant flex-shrink-0">Address</dt>
                            <dd class="text-on-surface text-right">{{ $user->address->full_address }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Verification status --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Status</h2>
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
                        <span class="font-body-sm text-body-sm text-on-surface">Email verified</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
                        <span class="font-body-sm text-body-sm text-on-surface">Application approved</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary" style="font-variation-settings: 'FILL' 1">check_circle</span>
                        <span class="font-body-sm text-body-sm text-on-surface">Signed in as {{ $user->roleLabel() }}</span>
                    </div>
                </div>
            </div>

            {{-- Coming next --}}
            <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant p-6 flex flex-col gap-4">
                <h2 class="font-headline-sm text-headline-sm text-on-surface">Coming to This Dashboard</h2>
                <ul class="flex flex-col gap-2">
                    @foreach($features as $feature)
                        <li class="flex items-start gap-2 font-body-sm text-body-sm text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px] text-outline mt-0.5">pending</span>
                            {{ $feature }}
                        </li>
                    @endforeach
                </ul>
            </div>

        </div>
    </div>
</div>
