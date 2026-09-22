@extends('layouts.app')

@section('title', 'Logistics Dashboard — Nexora Market')

@section('content')
    <x-dashboard-placeholder
        title="Logistics / Sorting Center"
        icon="local_shipping"
        accent="bg-secondary"
        :features="[
            'Rider management — approve/disapprove applications',
            'Confirm parcel pickup requests from sellers',
            'Management of incoming parcels',
            'Sorting of parcels by destination area',
            'Delivery assignment per area and per rider',
            'Delivery monitoring',
            'Generation of reports',
        ]" />

    @if(auth()->user()->sortingCenterProfile)
        <div class="w-full bg-surface pb-12">
            <div class="max-w-none px-margin-mobile md:px-margin-desktop">
                <div class="bg-surface-container-low rounded-2xl p-6">
                    <h3 class="font-headline-sm text-headline-sm text-on-surface mb-3">Your Facility</h3>
                    <dl class="font-body-sm text-body-sm flex gap-2">
                        <dt class="text-on-surface-variant">Business Name:</dt>
                        <dd class="text-on-surface">{{ auth()->user()->sortingCenterProfile->business_name }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    @endif
@endsection
