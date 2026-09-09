{{--
    Fixed, non-scrolling animated branding panel for split-layout auth screens
    (register, login). Intended to sit beside a scrollable form column inside
    a container sized h-[calc(100vh-4rem)].
--}}
<div class="hidden md:flex md:w-2/5 lg:w-1/3 h-full flex-shrink-0 relative overflow-hidden bg-gradient-to-br from-surface-container-low via-surface to-primary-fixed items-center justify-center">

    <div class="absolute w-[340px] h-[340px] rounded-full border border-dashed border-primary/20 nx-rotate-slow"></div>
    <div class="absolute w-[250px] h-[250px] rounded-full border border-dashed border-primary/30 nx-rotate-slow-reverse"></div>
    <div class="absolute w-[200px] h-[200px] rounded-full bg-primary/10 blur-3xl"></div>

    <span class="absolute top-[28%] left-[30%] w-2.5 h-2.5 rounded-full bg-primary nx-pulse-slow"></span>
    <span class="absolute bottom-[32%] right-[28%] w-2 h-2 rounded-full bg-tertiary nx-pulse-slow" style="animation-delay: 1s;"></span>
    <span class="absolute top-[38%] right-[24%] w-1.5 h-1.5 rounded-full bg-primary nx-pulse-slow" style="animation-delay: 1.8s;"></span>

    <div class="relative nx-float">
        <svg width="130" height="130" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 20px 30px rgba(0,88,190,0.35));">
            <defs>
                <linearGradient id="nxFace1" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5b9bff"/>
                    <stop offset="100%" stop-color="#0058be"/>
                </linearGradient>
                <linearGradient id="nxFace2" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#0058be"/>
                    <stop offset="100%" stop-color="#001a42"/>
                </linearGradient>
                <linearGradient id="nxFace3" x1="0" y1="1" x2="1" y2="0">
                    <stop offset="0%" stop-color="#adc6ff"/>
                    <stop offset="100%" stop-color="#2170e4"/>
                </linearGradient>
            </defs>
            <!-- Left bar -->
            <polygon points="30,20 62,20 62,180 30,180" fill="url(#nxFace1)"/>
            <!-- Right bar -->
            <polygon points="138,20 170,20 170,180 138,180" fill="url(#nxFace2)"/>
            <!-- Diagonal connector -->
            <polygon points="62,20 100,20 170,150 170,180 138,180 62,55" fill="url(#nxFace3)"/>
            <!-- Facet highlights -->
            <polygon points="30,20 62,20 62,55 30,90" fill="#ffffff" opacity="0.18"/>
            <polygon points="138,150 170,180 138,180" fill="#ffffff" opacity="0.12"/>
            <polygon points="62,55 100,20 170,150 150,150" fill="#ffffff" opacity="0.10"/>
        </svg>
    </div>

    @isset($caption)
        <div class="absolute bottom-16 left-0 right-0 px-10 text-center">
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $caption }}</p>
        </div>
    @endisset
</div>
