@props([
    'scannerId' => 'parcel-camera',
    'title' => 'Camera Barcode Scanner',
    'subtitle' => 'Point the camera at any waybill — full-camera detection automatically receives eligible parcels.',
    'autoSubmitInputId' => null,
    'autoSubmitFormId' => null,
    'manualFocusInputId' => null,
])

<div
    id="{{ $scannerId }}-container"
    class="hidden mt-4 rounded-2xl border border-[#c2c7d4] bg-[#f8fafc] p-4 shadow-sm"
    data-camera-scanner-container="{{ $scannerId }}"
    data-manual-input-id="{{ $manualFocusInputId ?? $autoSubmitInputId ?? '' }}"
>
    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
        <div>
            <h3 class="text-sm font-bold text-[#0f172a] flex items-center gap-2">
                <span class="material-symbols-outlined text-[#0058be] text-[18px]">photo_camera</span>
                <span>{{ $title }}</span>
            </h3>
            <p class="text-xs text-[#475569] mt-0.5">{{ $subtitle }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                type="button"
                data-camera-switch="{{ $scannerId }}"
                class="hidden inline-flex items-center gap-1.5 rounded-lg border border-[#cbd5e1] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#0f172a] hover:bg-[#f1f5f9] transition-colors"
                title="Switch between front, rear, or external camera"
            >
                <span class="material-symbols-outlined text-[15px]">cameraswitch</span>
                <span>Switch Camera</span>
            </button>

            <button
                type="button"
                data-camera-torch="{{ $scannerId }}"
                class="hidden inline-flex items-center gap-1.5 rounded-lg border border-[#cbd5e1] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#0f172a] hover:bg-[#f1f5f9] transition-colors"
                title="Toggle flashlight if supported"
            >
                <span class="material-symbols-outlined text-[15px]">flashlight_on</span>
                <span>Flashlight</span>
            </button>

            <button
                type="button"
                data-camera-retry="{{ $scannerId }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#0058be]/30 bg-[#eff6ff] px-2.5 py-1.5 text-xs font-semibold text-[#0058be] hover:bg-[#dbeafe] transition-colors"
            >
                <span class="material-symbols-outlined text-[15px]">refresh</span>
                <span>Retry</span>
            </button>

            <button
                type="button"
                data-camera-manual="{{ $scannerId }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#cbd5e1] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#334155] hover:bg-[#f1f5f9] transition-colors"
            >
                <span class="material-symbols-outlined text-[15px]">keyboard</span>
                <span>Manual Entry</span>
            </button>

            <button
                type="button"
                data-camera-close="{{ $scannerId }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-[#fecaca] bg-white px-2.5 py-1.5 text-xs font-semibold text-[#b91c1c] hover:bg-[#fef2f2] transition-colors"
            >
                <span class="material-symbols-outlined text-[15px]">close</span>
                <span>Close Camera</span>
            </button>
        </div>
    </div>

    {{-- Optional Zoom Bar (shown only if active camera supports optical/digital zoom) --}}
    <div
        data-camera-zoom-wrap="{{ $scannerId }}"
        class="hidden mb-3 flex items-center gap-3 rounded-lg border border-[#cbd5e1] bg-white px-3 py-2"
    >
        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#334155] shrink-0">
            <span class="material-symbols-outlined text-[16px] text-[#0058be]">zoom_in</span>
            <span>Zoom</span>
        </span>
        <button
            type="button"
            data-camera-zoom-step="{{ $scannerId }}"
            data-zoom-dir="-1"
            class="rounded border border-[#cbd5e1] bg-[#f8fafc] px-2 py-0.5 text-xs font-bold text-[#0f172a] hover:bg-[#e2e8f0]"
            aria-label="Zoom out"
        >−</button>
        <input
            type="range"
            data-camera-zoom-range="{{ $scannerId }}"
            min="1"
            max="3"
            step="0.1"
            value="1"
            class="flex-1 accent-[#0058be] cursor-pointer"
            aria-label="Camera zoom level"
        >
        <button
            type="button"
            data-camera-zoom-step="{{ $scannerId }}"
            data-zoom-dir="1"
            class="rounded border border-[#cbd5e1] bg-[#f8fafc] px-2 py-0.5 text-xs font-bold text-[#0f172a] hover:bg-[#e2e8f0]"
            aria-label="Zoom in"
        >+</button>
        <span data-camera-zoom-label="{{ $scannerId }}" class="font-mono text-[11px] font-bold text-[#0058be] min-w-[38px] text-right">1.0×</span>
    </div>

    {{-- Live Full-Camera Preview (No restrictive qrbox crop) --}}
    <div class="relative overflow-hidden rounded-xl border-2 border-[#0f172a]/20 bg-[#0f172a]">
        <div
            id="{{ $scannerId }}-reader"
            data-fullframe-scanner="true"
            class="w-full min-h-[280px] max-h-[440px] overflow-hidden"
        ></div>

        {{-- Subtle visual corner brackets only --}}
        <div class="pointer-events-none absolute inset-3 flex flex-col justify-between">
            <div class="flex justify-between">
                <span class="h-5 w-5 border-t-2 border-l-2 border-[#60a5fa]/75 rounded-tl-md"></span>
                <span class="h-5 w-5 border-t-2 border-r-2 border-[#60a5fa]/75 rounded-tr-md"></span>
            </div>
            <div class="flex justify-between">
                <span class="h-5 w-5 border-b-2 border-l-2 border-[#60a5fa]/75 rounded-bl-md"></span>
                <span class="h-5 w-5 border-b-2 border-r-2 border-[#60a5fa]/75 rounded-br-md"></span>
            </div>
        </div>
    </div>

    {{-- Single Clear Status Bar Below Camera (No Step Indicator) --}}
    <div
        id="{{ $scannerId }}-status-panel"
        data-camera-status-panel="{{ $scannerId }}"
        data-status-code="IDLE"
        class="mt-3 rounded-xl border border-[#cbd5e1] bg-white px-4 py-3 transition-colors"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-2.5">
                <span
                    id="{{ $scannerId }}-status-dot"
                    class="inline-block h-2.5 w-2.5 rounded-full bg-[#94a3b8]"
                ></span>
                <span
                    id="{{ $scannerId }}-status-badge"
                    class="inline-flex items-center rounded-md border border-[#cbd5e1] bg-[#f1f5f9] px-2.5 py-0.5 text-[11px] font-extrabold tracking-wider text-[#334155]"
                >
                    SCANNING
                </span>
                <span
                    id="{{ $scannerId }}-status-text"
                    class="text-xs font-semibold text-[#0f172a]"
                    aria-live="polite"
                >
                    Point camera at a waybill
                </span>
            </div>

            <span
                id="{{ $scannerId }}-decoded-pill"
                class="hidden inline-flex items-center gap-1 rounded-md border border-[#cbd5e1] bg-[#f8fafc] px-2.5 py-0.5 font-mono text-xs font-bold text-[#0f172a]"
            >
                <span id="{{ $scannerId }}-decoded-value"></span>
            </span>
        </div>
    </div>
</div>

@once
    <style>
        [data-fullframe-scanner="true"] video {
            width: 100% !important;
            height: auto !important;
            max-height: 400px;
            object-fit: contain;
            display: block;
        }
    </style>
    <script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
    <script>
        (function () {
            if (window.NexoraCameraScanner) {
                return;
            }

            const LOCAL_LIB_URL = @json(asset('vendor/html5-qrcode/html5-qrcode.min.js'));
            const CDN_FALLBACK_URL = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            const SAME_BARCODE_COOLDOWN_MS = 5000;
            const ASSIST_SWEEP_INTERVAL_MS = 300;

            const STATUS_META = {
                IDLE: {
                    label: 'IDLE',
                    panelClass: 'border-[#cbd5e1] bg-white',
                    badgeClass: 'border-[#cbd5e1] bg-[#f1f5f9] text-[#334155]',
                    dotClass: 'bg-[#94a3b8]',
                    defaultMessage: 'Camera scanner closed.'
                },
                SCANNING: {
                    label: 'SCANNING',
                    panelClass: 'border-[#93c5fd] bg-[#eff6ff]',
                    badgeClass: 'border-[#93c5fd] bg-[#dbeafe] text-[#0058be]',
                    dotClass: 'bg-[#0058be] animate-ping',
                    defaultMessage: 'Point camera at a waybill — waiting for a readable barcode'
                },
                RECEIVING: {
                    label: 'RECEIVING',
                    panelClass: 'border-[#93c5fd] bg-[#eff6ff]',
                    badgeClass: 'border-[#60a5fa] bg-[#dbeafe] text-[#0058be]',
                    dotClass: 'bg-[#0058be] animate-pulse',
                    defaultMessage: 'Barcode decoded; processing the parcel...'
                },
                RECEIVED: {
                    label: 'RECEIVED',
                    panelClass: 'border-[#86efac] bg-[#f0fdf4]',
                    badgeClass: 'border-[#4ade80] bg-[#dcfce7] text-[#166534]',
                    dotClass: 'bg-[#16a34a]',
                    defaultMessage: 'PARCEL RECEIVED — recorded in Laravel.'
                },
                ALREADY_RECEIVED: {
                    label: 'ALREADY RECEIVED',
                    panelClass: 'border-[#fcd34d] bg-[#fffbeb]',
                    badgeClass: 'border-[#fbbf24] bg-[#fef3c7] text-[#92400e]',
                    dotClass: 'bg-[#d97706]',
                    defaultMessage: 'Parcel was previously received.'
                },
                INVALID_BARCODE: {
                    label: 'INVALID BARCODE',
                    panelClass: 'border-[#fca5a5] bg-[#fef2f2]',
                    badgeClass: 'border-[#f87171] bg-[#fee2e2] text-[#991b1b]',
                    dotClass: 'bg-[#dc2626]',
                    defaultMessage: 'Tracking number not found.'
                },
                WRONG_HUB: {
                    label: 'WRONG HUB',
                    panelClass: 'border-[#fdba74] bg-[#fff7ed]',
                    badgeClass: 'border-[#fb923c] bg-[#ffedd5] text-[#9a3412]',
                    dotClass: 'bg-[#ea580c]',
                    defaultMessage: 'Parcel cannot be received at this hub.'
                },
                MANIFEST_REQUIRED: {
                    label: 'MANIFEST REQUIRED',
                    panelClass: 'border-[#fdba74] bg-[#fff7ed]',
                    badgeClass: 'border-[#fb923c] bg-[#ffedd5] text-[#924700]',
                    dotClass: 'bg-[#ea580c]',
                    defaultMessage: 'Parcel requires its assigned manifest receiving workflow.'
                },
                ERROR: {
                    label: 'ERROR',
                    panelClass: 'border-[#fca5a5] bg-[#fef2f2]',
                    badgeClass: 'border-[#f87171] bg-[#fee2e2] text-[#991b1b]',
                    dotClass: 'bg-[#dc2626]',
                    defaultMessage: 'Camera or server problem.'
                }
            };

            const activeSessions = new Map();
            let libLoadingPromise = null;
            let audioCtx = null;

            function normalizeBarcode(rawValue) {
                return String(rawValue || '')
                    .trim()
                    .replace(/[\x00-\x1F\x7F\s]+/g, '')
                    .toUpperCase();
            }

            function feedback(kind) {
                try {
                    if (typeof navigator !== 'undefined' && typeof navigator.vibrate === 'function') {
                        navigator.vibrate(kind === 'success' ? 75 : [45, 45, 45]);
                    }
                } catch (_) {}

                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    audioCtx = audioCtx || new AudioCtx();
                    if (audioCtx.state === 'suspended') {
                        audioCtx.resume().catch(() => {});
                    }

                    const now = audioCtx.currentTime;
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);

                    if (kind === 'success') {
                        osc.type = 'sine';
                        osc.frequency.setValueAtTime(880, now);
                        osc.frequency.setValueAtTime(1174.66, now + 0.08);
                        gain.gain.setValueAtTime(0.08, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.22);
                        osc.start(now);
                        osc.stop(now + 0.22);
                    } else {
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(260, now);
                        osc.frequency.setValueAtTime(210, now + 0.11);
                        gain.gain.setValueAtTime(0.09, now);
                        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.26);
                        osc.start(now);
                        osc.stop(now + 0.26);
                    }
                } catch (_) {}
            }

            async function canUseNativeCode128Detector() {
                try {
                    if (typeof window.BarcodeDetector === 'undefined' || typeof window.BarcodeDetector.getSupportedFormats !== 'function') {
                        return false;
                    }
                    const supported = await window.BarcodeDetector.getSupportedFormats();
                    return Array.isArray(supported) && supported.includes('code_128');
                } catch (_) {
                    return false;
                }
            }

            function buildScannerConfig(useNativeDetector) {
                const formats = typeof window.Html5QrcodeSupportedFormats !== 'undefined'
                    ? [
                        window.Html5QrcodeSupportedFormats.CODE_128,
                        window.Html5QrcodeSupportedFormats.QR_CODE,
                        window.Html5QrcodeSupportedFormats.CODE_39,
                        window.Html5QrcodeSupportedFormats.EAN_13,
                        window.Html5QrcodeSupportedFormats.ITF,
                        window.Html5QrcodeSupportedFormats.CODABAR,
                    ].filter((f) => f !== undefined)
                    : undefined;

                // Full-frame detection: omit qrbox & aspectRatio so any visible area can decode barcodes.
                return {
                    fps: 12,
                    disableFlip: false,
                    formatsToSupport: formats,
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: Boolean(useNativeDetector),
                    },
                };
            }

            function ensureLibraryLoaded() {
                if (typeof window.Html5Qrcode !== 'undefined') {
                    return Promise.resolve();
                }
                if (libLoadingPromise) {
                    return libLoadingPromise;
                }

                libLoadingPromise = new Promise((resolve, reject) => {
                    const loadScript = (src, onFail) => {
                        const script = document.createElement('script');
                        script.src = src;
                        script.async = true;
                        script.onload = () => {
                            if (typeof window.Html5Qrcode !== 'undefined') {
                                resolve();
                            } else {
                                onFail();
                            }
                        };
                        script.onerror = onFail;
                        document.head.appendChild(script);
                    };

                    loadScript(LOCAL_LIB_URL, () => {
                        loadScript(CDN_FALLBACK_URL, () => {
                            libLoadingPromise = null;
                            reject(new Error('Barcode decoder library failed to load.'));
                        });
                    });
                });

                return libLoadingPromise;
            }

            function setStatus(scannerId, statusCode, customMessage, decodedTracking) {
                const meta = STATUS_META[statusCode] || STATUS_META.IDLE;
                const panel = document.getElementById(scannerId + '-status-panel');
                const dot = document.getElementById(scannerId + '-status-dot');
                const badge = document.getElementById(scannerId + '-status-badge');
                const text = document.getElementById(scannerId + '-status-text');
                const decodedPill = document.getElementById(scannerId + '-decoded-pill');
                const decodedVal = document.getElementById(scannerId + '-decoded-value');

                if (!panel || !badge || !text) {
                    return;
                }

                panel.setAttribute('data-status-code', statusCode);
                panel.className = 'mt-3 rounded-xl border px-4 py-3 transition-colors ' + meta.panelClass;
                badge.className = 'inline-flex items-center rounded-md border px-2.5 py-0.5 text-[11px] font-extrabold tracking-wider ' + meta.badgeClass;
                badge.textContent = meta.label;
                if (dot) {
                    dot.className = 'inline-block h-2.5 w-2.5 rounded-full ' + meta.dotClass;
                }
                text.textContent = customMessage || meta.defaultMessage;

                if (decodedTracking && decodedPill && decodedVal) {
                    decodedVal.textContent = decodedTracking;
                    decodedPill.classList.remove('hidden');
                } else if (decodedTracking === null && decodedPill) {
                    decodedPill.classList.add('hidden');
                }
            }

            function scheduleResumeScanning(scannerId, delayMs) {
                const session = activeSessions.get(scannerId);
                if (!session) return;
                if (session.resumeTimer) {
                    clearTimeout(session.resumeTimer);
                }
                session.resumeTimer = setTimeout(() => {
                    if (session.isRunning && !session.isProcessing) {
                        setStatus(scannerId, 'SCANNING', 'Point camera at next waybill...', null);
                    }
                }, delayMs || 1800);
            }

            function clearTimers(session) {
                if (session.resumeTimer) {
                    clearTimeout(session.resumeTimer);
                    session.resumeTimer = null;
                }
                if (session.assistTimer) {
                    clearInterval(session.assistTimer);
                    session.assistTimer = null;
                }
            }

            function startAssistFullFrameSweep(scannerId, session, handleDecodedText) {
                if (session.assistTimer) {
                    clearInterval(session.assistTimer);
                }
                const modes = ['top', 'bottom', 'upper_mid', 'lower_mid', 'left', 'right', 'far_zoom', 'tilt_neg', 'tilt_pos', 'rot_90'];
                session.assistCanvas = session.assistCanvas || document.createElement('canvas');
                session.assistIndex = 0;
                session.assistBusy = false;

                session.assistTimer = setInterval(async () => {
                    if (!session.isRunning || session.isProcessing || session.assistBusy || !session.instance || !session.instance.qrcode) {
                        return;
                    }
                    const video = document.querySelector('#' + scannerId + '-reader video');
                    if (!video || !video.videoWidth || !video.videoHeight || video.readyState < 2) {
                        return;
                    }

                    session.assistBusy = true;
                    try {
                        const vw = video.videoWidth;
                        const vh = video.videoHeight;
                        const mode = modes[session.assistIndex % modes.length];
                        session.assistIndex++;

                        const canvas = session.assistCanvas;
                        const ctx = canvas.getContext('2d', { willReadFrequently: true });
                        if (!ctx) return;

                        if (mode === 'top') {
                            const h = Math.max(100, Math.floor(vh * 0.42));
                            canvas.width = vw;
                            canvas.height = h;
                            ctx.drawImage(video, 0, 0, vw, h, 0, 0, vw, h);
                        } else if (mode === 'bottom') {
                            const sy = Math.floor(vh * 0.58);
                            const h = Math.max(100, vh - sy);
                            canvas.width = vw;
                            canvas.height = h;
                            ctx.drawImage(video, 0, sy, vw, h, 0, 0, vw, h);
                        } else if (mode === 'upper_mid') {
                            const sy = Math.floor(vh * 0.18);
                            const h = Math.max(100, Math.floor(vh * 0.44));
                            canvas.width = vw;
                            canvas.height = h;
                            ctx.drawImage(video, 0, sy, vw, h, 0, 0, vw, h);
                        } else if (mode === 'lower_mid') {
                            const sy = Math.floor(vh * 0.38);
                            const h = Math.max(100, Math.floor(vh * 0.44));
                            canvas.width = vw;
                            canvas.height = h;
                            ctx.drawImage(video, 0, sy, vw, h, 0, 0, vw, h);
                        } else if (mode === 'left') {
                            const w = Math.max(200, Math.floor(vw * 0.65));
                            canvas.width = w;
                            canvas.height = vh;
                            ctx.drawImage(video, 0, 0, w, vh, 0, 0, w, vh);
                        } else if (mode === 'right') {
                            const sx = Math.floor(vw * 0.35);
                            const w = Math.max(200, vw - sx);
                            canvas.width = w;
                            canvas.height = vh;
                            ctx.drawImage(video, sx, 0, w, vh, 0, 0, w, vh);
                        } else if (mode === 'far_zoom') {
                            const sx = Math.floor(vw * 0.15);
                            const sy = Math.floor(vh * 0.15);
                            const sw = Math.floor(vw * 0.70);
                            const sh = Math.floor(vh * 0.70);
                            canvas.width = Math.min(1800, sw * 2);
                            canvas.height = Math.min(1200, sh * 2);
                            if ('filter' in ctx) ctx.filter = 'contrast(260%) grayscale(100%)';
                            ctx.drawImage(video, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
                            if ('filter' in ctx) ctx.filter = 'none';
                        } else if (mode === 'tilt_neg' || mode === 'tilt_pos') {
                            const angle = mode === 'tilt_neg' ? (-12 * Math.PI) / 180 : (12 * Math.PI) / 180;
                            canvas.width = vw;
                            canvas.height = vh;
                            ctx.fillStyle = '#ffffff';
                            ctx.fillRect(0, 0, vw, vh);
                            ctx.save();
                            ctx.translate(vw / 2, vh / 2);
                            ctx.rotate(angle);
                            ctx.drawImage(video, -vw / 2, -vh / 2, vw, vh);
                            ctx.restore();
                        } else if (mode === 'rot_90') {
                            canvas.width = vh;
                            canvas.height = vw;
                            ctx.save();
                            ctx.translate(vh / 2, vw / 2);
                            ctx.rotate(Math.PI / 2);
                            ctx.drawImage(video, -vw / 2, -vh / 2, vw, vh);
                            ctx.restore();
                        }

                        const result = await session.instance.qrcode.decodeAsync(canvas);
                        if (result && result.text) {
                            handleDecodedText(result.text);
                        }
                    } catch (_) {
                        // Sweep slice did not contain a decodable barcode; continue cleanly.
                    } finally {
                        session.assistBusy = false;
                    }
                }, ASSIST_SWEEP_INTERVAL_MS);
            }

            async function stopStreamOnly(session) {
                clearTimers(session);
                session.isRunning = false;
                session.isProcessing = false;

                const zoomWrap = document.querySelector('[data-camera-zoom-wrap="' + session.scannerId + '"]');
                if (zoomWrap) zoomWrap.classList.add('hidden');

                if (session && session.instance) {
                    try {
                        if (session.instance.isScanning) {
                            await session.instance.stop();
                        }
                    } catch (_) {}
                    try {
                        session.instance.clear();
                    } catch (_) {}
                }
            }

            async function close(scannerId) {
                const session = activeSessions.get(scannerId);
                if (session) {
                    await stopStreamOnly(session);
                    activeSessions.delete(scannerId);
                }

                const container = document.getElementById(scannerId + '-container');
                if (container) {
                    container.classList.add('hidden');
                }
                setStatus(scannerId, 'IDLE', null, null);
            }

            async function selectCameras(session) {
                try {
                    const cameras = await window.Html5Qrcode.getCameras();
                    session.cameras = Array.isArray(cameras) ? cameras : [];
                } catch (_) {
                    session.cameras = [];
                }

                const switchBtn = document.querySelector('[data-camera-switch="' + session.scannerId + '"]');
                if (switchBtn) {
                    switchBtn.classList.toggle('hidden', session.cameras.length <= 1);
                }

                if (session.cameras.length > 0 && session.cameraIndex === -1) {
                    const rearIdx = session.cameras.findIndex((c) => /back|rear|environment|wide|main/i.test(c.label || ''));
                    session.cameraIndex = rearIdx >= 0 ? rearIdx : 0;
                }
            }

            function setupHardwareCapabilities(scannerId, session, html5QrCode) {
                let caps = null;
                try {
                    caps = typeof html5QrCode.getRunningTrackCapabilities === 'function'
                        ? html5QrCode.getRunningTrackCapabilities()
                        : null;
                } catch (_) {}

                try {
                    if (caps && Array.isArray(caps.focusMode) && caps.focusMode.includes('continuous')) {
                        html5QrCode.applyVideoConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
                    }
                } catch (_) {}

                const torchBtn = document.querySelector('[data-camera-torch="' + scannerId + '"]');
                if (torchBtn) {
                    torchBtn.classList.toggle('hidden', !(caps && caps.torch));
                }

                const zoomWrap = document.querySelector('[data-camera-zoom-wrap="' + scannerId + '"]');
                const zoomRange = document.querySelector('[data-camera-zoom-range="' + scannerId + '"]');
                const zoomLabel = document.querySelector('[data-camera-zoom-label="' + scannerId + '"]');
                if (zoomWrap && zoomRange && caps && caps.zoom && typeof caps.zoom.max === 'number' && caps.zoom.max > caps.zoom.min) {
                    const minZ = caps.zoom.min ?? 1;
                    const maxZ = Math.min(caps.zoom.max, 5);
                    const stepZ = caps.zoom.step && caps.zoom.step > 0 ? caps.zoom.step : 0.1;
                    let currentZ = minZ;
                    try {
                        const settings = typeof html5QrCode.getRunningTrackSettings === 'function'
                            ? html5QrCode.getRunningTrackSettings()
                            : null;
                        if (settings && typeof settings.zoom === 'number') {
                            currentZ = settings.zoom;
                        }
                    } catch (_) {}

                    session.zoomBounds = { min: minZ, max: maxZ, step: stepZ };
                    zoomRange.min = String(minZ);
                    zoomRange.max = String(maxZ);
                    zoomRange.step = String(stepZ);
                    zoomRange.value = String(currentZ);
                    if (zoomLabel) zoomLabel.textContent = Number(currentZ).toFixed(1) + '×';
                    zoomWrap.classList.remove('hidden');
                } else if (zoomWrap) {
                    zoomWrap.classList.add('hidden');
                }
            }

            async function applyZoom(scannerId, targetZoom) {
                const session = activeSessions.get(scannerId);
                if (!session || !session.instance || !session.isRunning || !session.zoomBounds) {
                    return;
                }
                const clamped = Math.min(Math.max(Number(targetZoom), session.zoomBounds.min), session.zoomBounds.max);
                const zoomRange = document.querySelector('[data-camera-zoom-range="' + scannerId + '"]');
                const zoomLabel = document.querySelector('[data-camera-zoom-label="' + scannerId + '"]');
                try {
                    await session.instance.applyVideoConstraints({ advanced: [{ zoom: clamped }] });
                    if (zoomRange) zoomRange.value = String(clamped);
                    if (zoomLabel) zoomLabel.textContent = clamped.toFixed(1) + '×';
                } catch (_) {}
            }

            async function startCameraFeed(scannerId, session) {
                const container = document.getElementById(scannerId + '-container');
                const readerEl = document.getElementById(scannerId + '-reader');
                if (!container || !readerEl) {
                    return;
                }

                await stopStreamOnly(session);
                setStatus(scannerId, 'SCANNING', 'Starting camera...', null);

                try {
                    await ensureLibraryLoaded();
                } catch (err) {
                    setStatus(scannerId, 'ERROR', err.message || 'Scanner script unavailable. Use Manual Entry.', null);
                    return;
                }

                await selectCameras(session);

                const nativeSupported = await canUseNativeCode128Detector();
                const html5QrCode = new window.Html5Qrcode(scannerId + '-reader', { verbose: false });
                session.instance = html5QrCode;
                session.torchOn = false;

                const onDecode = async (decodedText) => {
                    const normalized = normalizeBarcode(decodedText);
                    if (!normalized || session.isProcessing) {
                        return;
                    }

                    const now = Date.now();
                    if (session.lastCode === normalized && (now - session.lastScannedAt) < SAME_BARCODE_COOLDOWN_MS) {
                        return;
                    }

                    if (session.resumeTimer) {
                        clearTimeout(session.resumeTimer);
                        session.resumeTimer = null;
                    }

                    session.isProcessing = true;
                    session.lastCode = normalized;
                    session.lastScannedAt = now;

                    setStatus(scannerId, 'RECEIVING', 'Processing parcel ' + normalized + '...', normalized);

                    try {
                        if (typeof session.onDecode === 'function') {
                            await session.onDecode(normalized, { scannerId: scannerId });
                        }
                    } finally {
                        session.isProcessing = false;
                        session.lastScannedAt = Date.now();
                    }
                };

                const onFrameFailure = () => {};

                const hasSpecificCamera = session.cameras.length > 0 && session.cameraIndex >= 0 && session.cameras[session.cameraIndex];
                const cameraIdOrFacing = hasSpecificCamera
                    ? session.cameras[session.cameraIndex].id
                    : { facingMode: 'environment' };

                const hdConstraints = hasSpecificCamera
                    ? {
                        deviceId: { exact: session.cameras[session.cameraIndex].id },
                        width: { ideal: 1920, min: 640 },
                        height: { ideal: 1080, min: 480 },
                        focusMode: { ideal: 'continuous' },
                    }
                    : {
                        facingMode: { ideal: 'environment' },
                        width: { ideal: 1920, min: 640 },
                        height: { ideal: 1080, min: 480 },
                        focusMode: { ideal: 'continuous' },
                    };

                try {
                    await html5QrCode.start(
                        cameraIdOrFacing,
                        Object.assign({}, buildScannerConfig(nativeSupported), { videoConstraints: hdConstraints }),
                        onDecode,
                        onFrameFailure
                    );
                } catch (primaryErr) {
                    try {
                        await html5QrCode.start(
                            cameraIdOrFacing,
                            buildScannerConfig(false),
                            onDecode,
                            onFrameFailure
                        );
                    } catch (fallbackErr) {
                        const msg = (fallbackErr && fallbackErr.message) || (primaryErr && primaryErr.message) || 'Camera permission denied or no camera device detected.';
                        setStatus(scannerId, 'ERROR', 'Camera error: ' + msg + ' — Use Manual Entry or USB scanner.', null);
                        return;
                    }
                }

                session.isRunning = true;
                setupHardwareCapabilities(scannerId, session, html5QrCode);
                setStatus(scannerId, 'SCANNING', 'Point camera at a waybill', null);
                startAssistFullFrameSweep(scannerId, session, onDecode);
            }

            async function open(scannerId, onDecode) {
                const container = document.getElementById(scannerId + '-container');
                if (!container) {
                    return;
                }

                container.classList.remove('hidden');

                let session = activeSessions.get(scannerId);
                if (!session) {
                    session = {
                        scannerId: scannerId,
                        instance: null,
                        cameras: [],
                        cameraIndex: -1,
                        isRunning: false,
                        isProcessing: false,
                        torchOn: false,
                        zoomBounds: null,
                        lastCode: null,
                        lastScannedAt: 0,
                        resumeTimer: null,
                        assistTimer: null,
                        assistCanvas: null,
                        assistIndex: 0,
                        assistBusy: false,
                        onDecode: onDecode,
                    };
                    activeSessions.set(scannerId, session);
                } else {
                    session.onDecode = onDecode;
                }

                await startCameraFeed(scannerId, session);
            }

            async function toggle(scannerId, onDecode) {
                const container = document.getElementById(scannerId + '-container');
                const session = activeSessions.get(scannerId);
                const isVisible = container && !container.classList.contains('hidden');
                if (isVisible || (session && session.isRunning)) {
                    await close(scannerId);
                    return false;
                }
                await open(scannerId, onDecode);
                return true;
            }

            async function retry(scannerId) {
                const session = activeSessions.get(scannerId);
                if (!session) {
                    return;
                }
                if (session.resumeTimer) clearTimeout(session.resumeTimer);
                session.lastCode = null;
                session.lastScannedAt = 0;

                if (session.isRunning) {
                    setStatus(scannerId, 'SCANNING', 'Point camera at a waybill', null);
                } else {
                    await startCameraFeed(scannerId, session);
                }
            }

            async function switchCamera(scannerId) {
                const session = activeSessions.get(scannerId);
                if (!session || session.cameras.length <= 1) {
                    return;
                }
                session.cameraIndex = (session.cameraIndex + 1) % session.cameras.length;
                session.lastCode = null;
                session.lastScannedAt = 0;
                await startCameraFeed(scannerId, session);
            }

            async function toggleTorch(scannerId) {
                const session = activeSessions.get(scannerId);
                if (!session || !session.instance || !session.isRunning) {
                    return;
                }
                const next = !session.torchOn;
                try {
                    await session.instance.applyVideoConstraints({ advanced: [{ torch: next }] });
                    session.torchOn = next;
                } catch (_) {}
            }

            document.addEventListener('click', function (e) {
                const closeBtn = e.target.closest('[data-camera-close]');
                if (closeBtn) {
                    close(closeBtn.getAttribute('data-camera-close'));
                    return;
                }

                const retryBtn = e.target.closest('[data-camera-retry]');
                if (retryBtn) {
                    retry(retryBtn.getAttribute('data-camera-retry'));
                    return;
                }

                const switchBtn = e.target.closest('[data-camera-switch]');
                if (switchBtn) {
                    switchCamera(switchBtn.getAttribute('data-camera-switch'));
                    return;
                }

                const torchBtn = e.target.closest('[data-camera-torch]');
                if (torchBtn) {
                    toggleTorch(torchBtn.getAttribute('data-camera-torch'));
                    return;
                }

                const zoomStepBtn = e.target.closest('[data-camera-zoom-step]');
                if (zoomStepBtn) {
                    const id = zoomStepBtn.getAttribute('data-camera-zoom-step');
                    const dir = Number(zoomStepBtn.getAttribute('data-zoom-dir') || 1);
                    const range = document.querySelector('[data-camera-zoom-range="' + id + '"]');
                    if (range) {
                        const step = Number(range.step || 0.2);
                        applyZoom(id, Number(range.value) + dir * Math.max(step, 0.2));
                    }
                    return;
                }

                const manualBtn = e.target.closest('[data-camera-manual]');
                if (manualBtn) {
                    const id = manualBtn.getAttribute('data-camera-manual');
                    const container = document.getElementById(id + '-container');
                    const inputId = container ? container.getAttribute('data-manual-input-id') : '';
                    close(id).then(() => {
                        const targetInput = inputId ? document.getElementById(inputId) : null;
                        if (targetInput) {
                            targetInput.focus();
                            targetInput.select();
                        }
                    });
                }
            });

            document.addEventListener('input', function (e) {
                const range = e.target.closest('[data-camera-zoom-range]');
                if (range) {
                    const id = range.getAttribute('data-camera-zoom-range');
                    applyZoom(id, Number(range.value));
                }
            });

            window.NexoraCameraScanner = {
                open: open,
                close: close,
                toggle: toggle,
                retry: retry,
                switchCamera: switchCamera,
                applyZoom: applyZoom,
                setStatus: setStatus,
                scheduleResumeScanning: scheduleResumeScanning,
                feedback: feedback,
                normalizeBarcode: normalizeBarcode,
                buildScannerConfig: buildScannerConfig,
                canUseNativeCode128Detector: canUseNativeCode128Detector,
                STATUS_META: STATUS_META,
            };
        })();
    </script>
@endonce
