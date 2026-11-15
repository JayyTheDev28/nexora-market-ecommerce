<div id="{{ $scannerId ?? 'nexora-camera-scanner' }}-panel" class="hidden mt-4 rounded-2xl border-2 border-primary/30 bg-slate-950 p-4 text-white">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-xs font-bold tracking-wide uppercase">Live Barcode Camera Scanner (Code 128 / QR)</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                    id="{{ $scannerId ?? 'nexora-camera-scanner' }}-torch-btn"
                    class="hidden px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-[11px] font-semibold text-white cursor-pointer">
                Flashlight: OFF
            </button>
            <button type="button"
                    id="{{ $scannerId ?? 'nexora-camera-scanner' }}-close-btn"
                    class="px-3 py-1.5 rounded-lg bg-rose-500/20 hover:bg-rose-500/30 text-[11px] font-semibold text-rose-200 cursor-pointer">
                Close Camera
            </button>
        </div>
    </div>

    <div id="{{ $scannerId ?? 'nexora-camera-scanner' }}-reader" class="w-full max-w-md mx-auto overflow-hidden rounded-xl bg-black min-h-[240px]"></div>

    <p id="{{ $scannerId ?? 'nexora-camera-scanner' }}-status" class="mt-2 text-center text-[11px] text-slate-300">
        Point the camera at a printed Nexora Code 128 waybill barcode.
    </p>
</div>

@once
    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" defer></script>
        <script>
            window.NexoraCameraScanner = {
                instances: {},

                async toggle(scannerId, onDecoded) {
                    const panel = document.getElementById(scannerId + '-panel');
                    if (!panel) return;
                    if (!panel.classList.contains('hidden')) {
                        await this.stop(scannerId);
                        return;
                    }
                    await this.start(scannerId, onDecoded);
                },

                async start(scannerId, onDecoded) {
                    const panel = document.getElementById(scannerId + '-panel');
                    const statusEl = document.getElementById(scannerId + '-status');
                    const torchBtn = document.getElementById(scannerId + '-torch-btn');
                    const closeBtn = document.getElementById(scannerId + '-close-btn');
                    if (!panel) return;

                    panel.classList.remove('hidden');
                    if (closeBtn) {
                        closeBtn.onclick = () => this.stop(scannerId);
                    }

                    if (typeof Html5Qrcode === 'undefined') {
                        statusEl.textContent = 'Camera scanner library failed to load (offline). Use a USB/Bluetooth barcode scanner or manual entry.';
                        statusEl.className = 'mt-2 text-center text-[11px] text-amber-300';
                        return;
                    }

                    await this.stop(scannerId, false);

                    const readerId = scannerId + '-reader';
                    const html5QrCode = new Html5Qrcode(readerId, {
                        formatsToSupport: [
                            Html5QrcodeSupportedFormats.CODE_128,
                            Html5QrcodeSupportedFormats.QR_CODE,
                            Html5QrcodeSupportedFormats.CODE_39,
                        ],
                        verbose: false,
                    });

                    let lastCode = '';
                    let lastAt = 0;
                    let torchOn = false;

                    this.instances[scannerId] = { html5QrCode };
                    statusEl.textContent = 'Starting camera...';
                    statusEl.className = 'mt-2 text-center text-[11px] text-slate-300';

                    try {
                        await html5QrCode.start(
                            { facingMode: 'environment' },
                            {
                                fps: 12,
                                qrbox: { width: 280, height: 130 },
                                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                            },
                            (decodedText) => {
                                const normalized = (decodedText || '').trim();
                                const now = Date.now();
                                if (!normalized || (normalized === lastCode && now - lastAt < 2500)) {
                                    return;
                                }
                                lastCode = normalized;
                                lastAt = now;
                                statusEl.textContent = 'Detected barcode: ' + normalized;
                                statusEl.className = 'mt-2 text-center text-[11px] text-emerald-300 font-semibold';
                                onDecoded(normalized);
                            },
                            () => {}
                        );

                        statusEl.textContent = 'Camera ready. Align barcode inside the frame.';
                        try {
                            const caps = html5QrCode.getRunningTrackCapabilities();
                            if (caps && caps.torch && torchBtn) {
                                torchBtn.classList.remove('hidden');
                                torchBtn.onclick = async () => {
                                    torchOn = !torchOn;
                                    try {
                                        await html5QrCode.applyVideoConstraints({ advanced: [{ torch: torchOn }] });
                                        torchBtn.textContent = torchOn ? 'Flashlight: ON' : 'Flashlight: OFF';
                                    } catch (_) {
                                        torchOn = !torchOn;
                                    }
                                };
                            }
                        } catch (_) {}
                    } catch (err) {
                        statusEl.textContent = 'Camera unavailable or permission denied (' + (err?.message || err) + '). Use USB scanner or manual entry.';
                        statusEl.className = 'mt-2 text-center text-[11px] text-rose-300';
                    }
                },

                async stop(scannerId, hidePanel = true) {
                    const inst = this.instances[scannerId];
                    const torchBtn = document.getElementById(scannerId + '-torch-btn');
                    const panel = document.getElementById(scannerId + '-panel');
                    if (torchBtn) {
                        torchBtn.classList.add('hidden');
                        torchBtn.textContent = 'Flashlight: OFF';
                    }
                    if (inst && inst.html5QrCode) {
                        try {
                            await inst.html5QrCode.stop();
                        } catch (_) {}
                        try {
                            inst.html5QrCode.clear();
                        } catch (_) {}
                        delete this.instances[scannerId];
                    }
                    if (hidePanel && panel) {
                        panel.classList.add('hidden');
                    }
                }
            };
        </script>
    @endpush
@endonce
